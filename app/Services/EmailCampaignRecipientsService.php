<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignSend;
use App\Models\Order;
use Illuminate\Support\Collection;

class EmailCampaignRecipientsService
{
    /**
     * Get recipients for a tenant and filter config.
     * Returns collection of { email, user_id, name } with unique emails (valid only).
     *
     * @param  array{all_customers?: bool, product_ids?: array<int|string>}  $filterConfig
     * @return Collection<int, array{email: string, user_id: int|null, name: string}>
     */
    public function getRecipients(?int $tenantId, array $filterConfig): Collection
    {
        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if (! empty($filterConfig['product_ids'])) {
            $query->whereIn('product_id', $filterConfig['product_ids']);
        }

        $orders = $query->with('user:id,name,email')->get();
        $byEmail = [];
        foreach ($orders as $order) {
            $email = trim((string) $order->email);
            if ($email === '' || ! str_contains($email, '@')) {
                continue;
            }
            if (isset($byEmail[$email])) {
                continue;
            }
            $name = $order->user?->name ?? '';
            if ($name === '' && $order->user_id) {
                $name = '';
            }
            $byEmail[$email] = [
                'email' => $email,
                'user_id' => $order->user_id,
                'name' => $name ?: $email,
            ];
        }

        return collect(array_values($byEmail));
    }

    /**
     * Libera reservas "queued" antigas (job perdido / worker parado) para nova tentativa.
     */
    public function reclaimStaleQueued(EmailCampaign $campaign, int $minutes = 15): int
    {
        return $campaign->emailCampaignSends()
            ->where('status', EmailCampaignSend::STATUS_QUEUED)
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->delete();
    }

    /**
     * Próximo lote de destinatários ainda não enviados / não enfileirados / sem falha permanente.
     *
     * @return Collection<int, array{email: string, user_id: int|null, name: string}>
     */
    public function getNextRecipientsForCampaign(EmailCampaign $campaign, int $limit = 30): Collection
    {
        $filterConfig = $campaign->filter_config ?? [];
        $tenantId = $campaign->tenant_id;
        $all = $this->getRecipients($tenantId, $filterConfig);

        $blockedEmails = $campaign->emailCampaignSends()
            ->whereIn('status', [
                EmailCampaignSend::STATUS_SENT,
                EmailCampaignSend::STATUS_FAILED,
                EmailCampaignSend::STATUS_QUEUED,
            ])
            ->pluck('email')
            ->flip();

        $pending = $all->filter(fn ($r) => ! $blockedEmails->has($r['email']));

        return $pending->take($limit)->values();
    }
}
