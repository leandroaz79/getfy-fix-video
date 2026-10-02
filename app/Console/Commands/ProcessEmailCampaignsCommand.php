<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignEmailJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignSend;
use App\Services\EmailCampaignRecipientsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEmailCampaignsCommand extends Command
{
    protected $signature = 'email-campaign:process';

    protected $description = 'Processa campanhas de e-mail em envio (até 30 destinatários por campanha por minuto).';

    /** Segundos entre cada job do lote (espalha o envio e reduz rate limit do SMTP). */
    private const STAGGER_SECONDS = 2;

    public function handle(EmailCampaignRecipientsService $recipientsService): int
    {
        try {
            $this->resumeRateLimitedCampaigns();

            $campaigns = EmailCampaign::sending()->get();

            foreach ($campaigns as $campaign) {
                $this->processCampaign($campaign, $recipientsService);
            }
        } catch (\Throwable $e) {
            Log::error('email-campaign:process falhou.', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Não derruba o schedule inteiro: reporta e sai com sucesso controlado.
            $this->error($e->getMessage());

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    private function resumeRateLimitedCampaigns(): void
    {
        EmailCampaign::query()
            ->where('status', EmailCampaign::STATUS_PAUSED)
            ->whereNotNull('backoff_until')
            ->where('backoff_until', '<=', now())
            ->each(function (EmailCampaign $campaign): void {
                $campaign->update([
                    'status' => EmailCampaign::STATUS_SENDING,
                    'paused_at' => null,
                    'backoff_until' => null,
                    'last_error' => null,
                ]);
            });
    }

    private function processCampaign(EmailCampaign $campaign, EmailCampaignRecipientsService $recipientsService): void
    {
        $campaign->refresh();

        if (! $campaign->isSending()) {
            return;
        }

        if ($campaign->isInBackoff()) {
            return;
        }

        if ($campaign->backoff_until !== null) {
            $campaign->update([
                'backoff_until' => null,
                'last_error' => null,
            ]);
        }

        $recipientsService->reclaimStaleQueued($campaign);

        $recipients = $recipientsService->getNextRecipientsForCampaign($campaign, 30);

        if ($recipients->isEmpty()) {
            $stillQueued = $campaign->emailCampaignSends()
                ->where('status', EmailCampaignSend::STATUS_QUEUED)
                ->exists();

            if (! $stillQueued) {
                $campaign->update([
                    'status' => EmailCampaign::STATUS_SENT,
                    'sent_at' => now(),
                    'last_error' => null,
                    'backoff_until' => null,
                ]);
            }

            return;
        }

        foreach ($recipients as $index => $r) {
            EmailCampaignSend::updateOrCreate(
                [
                    'email_campaign_id' => $campaign->id,
                    'email' => $r['email'],
                ],
                [
                    'user_id' => $r['user_id'] ?? null,
                    'status' => EmailCampaignSend::STATUS_QUEUED,
                    'error_message' => null,
                    'sent_at' => null,
                ]
            );

            SendCampaignEmailJob::dispatch(
                $campaign->id,
                $r['email'],
                $r['user_id'] ?? null,
                $r['name'] ?? $r['email']
            )->delay(now()->addSeconds($index * self::STAGGER_SECONDS));
        }
    }
}
