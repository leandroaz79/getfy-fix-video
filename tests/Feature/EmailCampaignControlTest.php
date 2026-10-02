<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignSend;
use App\Models\Order;
use App\Models\User;
use App\Services\EmailCampaignRecipientsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EmailCampaignControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);
    }

    public function test_user_can_pause_sending_campaign(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR, 'tenant_id' => 1]);
        $campaign = $this->createCampaign($user->tenant_id, EmailCampaign::STATUS_SENDING);

        $this->actingAs($user)
            ->post("/email-marketing/{$campaign->id}/pause")
            ->assertRedirect(route('email-marketing.index'));

        $this->assertSame(EmailCampaign::STATUS_PAUSED, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->paused_at);
    }

    public function test_user_can_cancel_sending_campaign(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR, 'tenant_id' => 1]);
        $campaign = $this->createCampaign($user->tenant_id, EmailCampaign::STATUS_SENDING);

        $this->actingAs($user)
            ->post("/email-marketing/{$campaign->id}/cancel")
            ->assertRedirect(route('email-marketing.index'));

        $this->assertSame(EmailCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
    }

    public function test_user_can_resume_paused_campaign(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_INFOPRODUTOR, 'tenant_id' => 1]);
        $campaign = $this->createCampaign($user->tenant_id, EmailCampaign::STATUS_PAUSED);
        $campaign->update(['backoff_until' => now()->addMinutes(5)]);

        $this->actingAs($user)
            ->post("/email-marketing/{$campaign->id}/resume")
            ->assertRedirect(route('email-marketing.index'));

        $fresh = $campaign->fresh();
        $this->assertSame(EmailCampaign::STATUS_SENDING, $fresh->status);
        $this->assertNull($fresh->paused_at);
        $this->assertNull($fresh->backoff_until);
    }

    public function test_failed_recipient_is_not_retried_automatically(): void
    {
        $product = $this->createTestProduct();
        $campaign = $this->createCampaign($product->tenant_id, EmailCampaign::STATUS_SENDING);

        Order::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100,
            'currency' => 'BRL',
            'email' => 'cliente@example.com',
            'gateway' => 'cajupay',
        ]);

        EmailCampaignSend::create([
            'email_campaign_id' => $campaign->id,
            'email' => 'cliente@example.com',
            'status' => EmailCampaignSend::STATUS_FAILED,
            'error_message' => 'Invalid recipient',
        ]);

        $recipients = app(EmailCampaignRecipientsService::class)
            ->getNextRecipientsForCampaign($campaign->fresh(), 30);

        $this->assertCount(0, $recipients);
    }

    public function test_queued_recipient_is_not_dispatched_again(): void
    {
        $product = $this->createTestProduct();
        $campaign = $this->createCampaign($product->tenant_id, EmailCampaign::STATUS_SENDING);

        Order::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100,
            'currency' => 'BRL',
            'email' => 'cliente@example.com',
            'gateway' => 'cajupay',
        ]);

        EmailCampaignSend::create([
            'email_campaign_id' => $campaign->id,
            'email' => 'cliente@example.com',
            'status' => EmailCampaignSend::STATUS_QUEUED,
        ]);

        $recipients = app(EmailCampaignRecipientsService::class)
            ->getNextRecipientsForCampaign($campaign->fresh(), 30);

        $this->assertCount(0, $recipients);
    }

    public function test_send_job_soft_backoff_on_rate_limit_keeps_sending_status(): void
    {
        config(['queue.default' => 'sync']);

        $campaign = $this->createCampaign(1, EmailCampaign::STATUS_SENDING);

        $pending = Mockery::mock();
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('429 Too Many Requests'));

        $mailer = Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('to')->once()->with('fail@example.com')->andReturn($pending);

        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);

        $job = new SendCampaignEmailJob($campaign->id, 'fail@example.com', null, 'Fail');
        $job->handle(app(\App\Services\TenantMailConfigService::class));

        $fresh = $campaign->fresh();
        $this->assertSame(EmailCampaign::STATUS_SENDING, $fresh->status);
        $this->assertNotNull($fresh->backoff_until);
        $this->assertTrue($fresh->backoff_until->isFuture());
        $this->assertStringContainsString('Retomando automaticamente', (string) $fresh->last_error);

        // Sync libera a reserva para o process reenfileirar após o backoff.
        $this->assertNull(
            EmailCampaignSend::query()
                ->where('email_campaign_id', $campaign->id)
                ->where('email', 'fail@example.com')
                ->first()
        );
    }

    public function test_process_skips_campaign_during_backoff_and_resumes_after(): void
    {
        Queue::fake();

        $product = $this->createTestProduct();
        $campaign = $this->createCampaign($product->tenant_id, EmailCampaign::STATUS_SENDING);
        $campaign->update(['backoff_until' => now()->addMinutes(2)]);

        Order::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100,
            'currency' => 'BRL',
            'email' => 'cliente@example.com',
            'gateway' => 'cajupay',
        ]);

        Artisan::call('email-campaign:process');
        Queue::assertNothingPushed();

        $campaign->update(['backoff_until' => now()->subMinute()]);
        Artisan::call('email-campaign:process');

        Queue::assertPushed(SendCampaignEmailJob::class, 1);

        $this->assertSame(
            EmailCampaignSend::STATUS_QUEUED,
            EmailCampaignSend::query()
                ->where('email_campaign_id', $campaign->id)
                ->where('email', 'cliente@example.com')
                ->value('status')
        );
    }

    public function test_process_auto_resumes_paused_campaign_when_backoff_expires(): void
    {
        Queue::fake();

        $product = $this->createTestProduct();
        $campaign = $this->createCampaign($product->tenant_id, EmailCampaign::STATUS_PAUSED);
        $campaign->update([
            'backoff_until' => now()->subMinute(),
            'paused_at' => now()->subMinutes(3),
            'last_error' => 'Limite temporário',
        ]);

        Order::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'status' => 'completed',
            'amount' => 100,
            'currency' => 'BRL',
            'email' => 'auto@example.com',
            'gateway' => 'cajupay',
        ]);

        Artisan::call('email-campaign:process');

        $fresh = $campaign->fresh();
        $this->assertSame(EmailCampaign::STATUS_SENDING, $fresh->status);
        $this->assertNull($fresh->backoff_until);
        Queue::assertPushed(SendCampaignEmailJob::class, 1);
    }

    private function createCampaign(?int $tenantId, string $status): EmailCampaign
    {
        return EmailCampaign::create([
            'tenant_id' => $tenantId,
            'name' => 'Campanha teste',
            'subject' => 'Assunto',
            'body_html' => '<p>Olá {nome}</p>',
            'filter_config' => ['all_customers' => true],
            'status' => $status,
            'total_recipients' => 1,
            'sent_count' => 0,
        ]);
    }
}
