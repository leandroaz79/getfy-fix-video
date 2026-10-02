<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignSend;
use App\Services\TenantMailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Pausa automática (manual) após N falhas permanentes recentes. */
    private const AUTO_PAUSE_FAILURE_THRESHOLD = 5;

    /** Minutos de espera automática após rate limit do provedor. */
    private const RATE_LIMIT_BACKOFF_MINUTES = 2;

    public int $tries = 5;

    public function __construct(
        public int $emailCampaignId,
        public string $email,
        public ?int $userId,
        public string $name
    ) {}

    public function handle(TenantMailConfigService $mailConfig): void
    {
        $campaign = EmailCampaign::find($this->emailCampaignId);
        if (! $campaign) {
            return;
        }

        if (! $campaign->isSending()) {
            $this->releaseQueuedReservation($campaign);

            return;
        }

        if ($campaign->isInBackoff()) {
            $this->deferDuringBackoff($campaign);

            return;
        }

        $existing = EmailCampaignSend::query()
            ->where('email_campaign_id', $campaign->id)
            ->where('email', $this->email)
            ->first();

        if ($existing?->status === EmailCampaignSend::STATUS_SENT) {
            return;
        }

        $mailConfig->applyMailerConfigForTenant($campaign->tenant_id, [], null);

        $body = str_replace(
            ['{nome}', '{email}'],
            [e($this->name), e($this->email)],
            $campaign->body_html
        );

        try {
            Mail::mailer('smtp')->to($this->email)->send(new CampaignMail($campaign->subject, $body));
        } catch (\Throwable $e) {
            $this->recordFailure($campaign, $e);

            return;
        }

        EmailCampaignSend::updateOrCreate(
            [
                'email_campaign_id' => $campaign->id,
                'email' => $this->email,
            ],
            [
                'user_id' => $this->userId,
                'status' => EmailCampaignSend::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]
        );

        if ($existing?->status !== EmailCampaignSend::STATUS_SENT) {
            $campaign->increment('sent_count');
        }

        // Só limpa last_error informativo de backoff; não apaga se outra falha recente existir.
        if ($campaign->backoff_until === null) {
            $campaign->update(['last_error' => null]);
        }
    }

    private function recordFailure(EmailCampaign $campaign, \Throwable $e): void
    {
        $message = $e->getMessage();

        Log::warning('SendCampaignEmailJob: falha ao enviar.', [
            'campaign_id' => $this->emailCampaignId,
            'email' => $this->email,
            'message' => $message,
        ]);

        if ($this->isRateLimitError($e) || $this->isTransientTransportError($e)) {
            $this->applySoftBackoff($campaign, $message);

            return;
        }

        EmailCampaignSend::updateOrCreate(
            [
                'email_campaign_id' => $campaign->id,
                'email' => $this->email,
            ],
            [
                'user_id' => $this->userId,
                'status' => EmailCampaignSend::STATUS_FAILED,
                'error_message' => mb_substr($message, 0, 2000),
                'sent_at' => null,
            ]
        );

        $recentFailures = EmailCampaignSend::query()
            ->where('email_campaign_id', $campaign->id)
            ->where('status', EmailCampaignSend::STATUS_FAILED)
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentFailures >= self::AUTO_PAUSE_FAILURE_THRESHOLD && $campaign->isSending()) {
            $campaign->update([
                'status' => EmailCampaign::STATUS_PAUSED,
                'paused_at' => now(),
                'last_error' => 'Muitas falhas consecutivas. Campanha pausada automaticamente. Detalhe: '
                    . mb_substr($message, 0, 500),
            ]);
        } else {
            $campaign->update([
                'last_error' => mb_substr($message, 0, 500),
            ]);
        }
    }

    /**
     * Rate limit / erro transitório: libera o destinatário e espera sozinho (sem clique manual).
     */
    private function applySoftBackoff(EmailCampaign $campaign, string $message): void
    {
        $until = now()->addMinutes(self::RATE_LIMIT_BACKOFF_MINUTES);

        // Mantém (ou recria) a reserva queued — o envio será retentado após o backoff.
        EmailCampaignSend::updateOrCreate(
            [
                'email_campaign_id' => $campaign->id,
                'email' => $this->email,
            ],
            [
                'user_id' => $this->userId,
                'status' => EmailCampaignSend::STATUS_QUEUED,
                'error_message' => mb_substr($message, 0, 2000),
                'sent_at' => null,
            ]
        );

        if ($campaign->isSending() || $campaign->isPaused()) {
            $campaign->update([
                'status' => EmailCampaign::STATUS_SENDING,
                'paused_at' => null,
                'backoff_until' => $campaign->backoff_until?->isFuture()
                    ? $campaign->backoff_until
                    : $until,
                'last_error' => 'Limite temporário do provedor de e-mail. Retomando automaticamente em alguns minutos. Detalhe: '
                    . mb_substr($message, 0, 400),
            ]);
        }

        $this->deferDuringBackoff($campaign->fresh());
    }

    /**
     * Adia o job até o fim do backoff (fila async) ou libera a reserva (fila sync).
     */
    private function deferDuringBackoff(EmailCampaign $campaign): void
    {
        if (config('queue.default') === 'sync') {
            // Sync ignora delay/release — libera para o process reenfileirar depois do backoff.
            $this->releaseQueuedReservation($campaign);

            return;
        }

        $seconds = 60;
        if ($campaign->backoff_until !== null) {
            $seconds = max(30, $campaign->backoff_until->getTimestamp() - time());
        }

        $this->release($seconds);
    }

    private function releaseQueuedReservation(EmailCampaign $campaign): void
    {
        EmailCampaignSend::query()
            ->where('email_campaign_id', $campaign->id)
            ->where('email', $this->email)
            ->where('status', EmailCampaignSend::STATUS_QUEUED)
            ->delete();
    }

    private function isRateLimitError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        foreach ([
            'rate limit',
            'too many',
            '429',
            'throttl',
            '550 5.4.6',
            '451 4.7.1',
            '421 4.7.0',
            '421 4.7.1',
            '452 4.2.1',
            'quota exceeded',
            'sending quota',
            'daily limit',
            'limite de envio',
            'excedeu o limite',
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isTransientTransportError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        foreach ([
            'connection could not be established',
            'timed out',
            'timeout',
            'temporarily rejected',
            'try again later',
            'broken pipe',
            'connection reset',
            'expected response code',
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return false;
    }
}
