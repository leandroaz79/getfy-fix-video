<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Buffer de webhooks CajuPay "paid" que chegaram antes do pedido existir no Getfy
 * (corrida confirm-order × webhook). O confirm-order consome o buffer e completa o pedido.
 */
final class CajuPayPaidSessionBuffer
{
    private const TTL_MINUTES = 45;

    public static function cacheKeyForSession(string $checkoutSessionId): string
    {
        return 'cajupay_paid_session.'.trim($checkoutSessionId);
    }

    public static function cacheKeyForPayment(string $paymentId): string
    {
        return 'cajupay_paid_payment.'.trim($paymentId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function store(string $checkoutSessionId, string $paymentId, array $payload): void
    {
        $checkoutSessionId = trim($checkoutSessionId);
        $paymentId = trim($paymentId);
        $entry = [
            'stored_at' => now()->toIso8601String(),
            'checkout_session_id' => $checkoutSessionId !== '' ? $checkoutSessionId : null,
            'payment_id' => $paymentId !== '' ? $paymentId : null,
            'payload' => $payload,
        ];
        $ttl = now()->addMinutes(self::TTL_MINUTES);

        if ($checkoutSessionId !== '') {
            Cache::put(self::cacheKeyForSession($checkoutSessionId), $entry, $ttl);
        }
        if ($paymentId !== '') {
            Cache::put(self::cacheKeyForPayment($paymentId), $entry, $ttl);
        }

        Log::info('CajuPayPaidSessionBuffer: paid bufferizado (pedido ainda inexistente)', [
            'checkout_session_id' => $checkoutSessionId,
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pullForSession(?string $checkoutSessionId, ?string $paymentId = null): ?array
    {
        $checkoutSessionId = is_string($checkoutSessionId) ? trim($checkoutSessionId) : '';
        $paymentId = is_string($paymentId) ? trim($paymentId) : '';

        $entry = null;
        if ($checkoutSessionId !== '') {
            $entry = Cache::pull(self::cacheKeyForSession($checkoutSessionId));
        }
        if ((! is_array($entry) || $entry === []) && $paymentId !== '') {
            $entry = Cache::pull(self::cacheKeyForPayment($paymentId));
        }

        return is_array($entry) && $entry !== [] ? $entry : null;
    }

    /**
     * Aplica buffer ao pedido (se existir) via ProcessPaymentWebhook.
     *
     * @return bool true se disparou conclusão
     */
    public static function applyToOrderIfBuffered(\App\Models\Order $order): bool
    {
        if ($order->gateway !== 'cajupay' || $order->status === 'completed') {
            return false;
        }

        $meta = is_array($order->metadata) ? $order->metadata : [];
        $sessionId = trim((string) ($meta['cajupay_checkout_session_id'] ?? $order->gateway_id ?? ''));
        $paymentId = trim((string) ($meta['cajupay_payment_id'] ?? ''));

        $entry = self::pullForSession($sessionId !== '' ? $sessionId : null, $paymentId !== '' ? $paymentId : null);
        if ($entry === null) {
            return false;
        }

        $bufferedPaymentId = trim((string) ($entry['payment_id'] ?? ''));
        $dispatchId = $bufferedPaymentId !== ''
            ? $bufferedPaymentId
            : (string) ($order->gateway_id ?: $sessionId);

        if ($dispatchId === '') {
            return false;
        }

        if ($bufferedPaymentId !== '') {
            CajuPayPaymentId::persistOnOrder($order, $bufferedPaymentId);
            $order->refresh();
        }

        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $payload['webhook_source'] = 'cajupay_hmac_verified';
        $payload['getfy_order_id'] = $order->id;
        $payload['source'] = 'cajupay_paid_buffer';

        Log::info('CajuPayPaidSessionBuffer: aplicando paid bufferizado ao pedido', [
            'order_id' => $order->id,
            'dispatch_id' => $dispatchId,
        ]);

        \App\Jobs\ProcessPaymentWebhook::dispatchSync(
            'cajupay',
            $dispatchId,
            'order.paid',
            'paid',
            $payload
        );

        return true;
    }
}
