<?php

namespace App\Jobs;

use App\Models\Order;
use App\Support\CajuPayPaidSessionBuffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reprocessa paid bufferizado quando o webhook chegou antes do pedido existir.
 */
class RetryCajuPayBufferedPaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 15, 30, 60, 120];

    public function __construct(
        public string $checkoutSessionId = '',
        public string $paymentId = ''
    ) {}

    public function handle(): void
    {
        $sessionId = trim($this->checkoutSessionId);
        $paymentId = trim($this->paymentId);

        $order = null;
        if ($sessionId !== '') {
            $order = Order::where('gateway', 'cajupay')
                ->where(function ($q) use ($sessionId) {
                    $q->where('gateway_id', $sessionId)
                        ->orWhere('metadata->cajupay_checkout_session_id', $sessionId);
                })
                ->orderByDesc('id')
                ->first();
        }
        if ($order === null && $paymentId !== '') {
            $order = Order::where('gateway', 'cajupay')
                ->where(function ($q) use ($paymentId) {
                    $q->where('gateway_id', $paymentId)
                        ->orWhere('metadata->cajupay_payment_id', $paymentId);
                })
                ->orderByDesc('id')
                ->first();
        }

        if ($order === null) {
            // Ainda sem pedido: mantém buffer e tenta de novo (até esgotar tries).
            if ($this->attempts() < $this->tries) {
                Log::info('RetryCajuPayBufferedPaid: pedido ainda inexistente, reagendando', [
                    'session_id' => $sessionId,
                    'payment_id' => $paymentId,
                    'attempt' => $this->attempts(),
                ]);
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 30);
            }

            return;
        }

        if ($order->status === 'completed') {
            CajuPayPaidSessionBuffer::pullForSession($sessionId !== '' ? $sessionId : null, $paymentId !== '' ? $paymentId : null);

            return;
        }

        $applied = CajuPayPaidSessionBuffer::applyToOrderIfBuffered($order->fresh());
        if (! $applied && $order->fresh()->status !== 'completed') {
            Log::warning('RetryCajuPayBufferedPaid: pedido encontrado mas buffer já consumido/sem paid', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'payment_id' => $paymentId,
            ]);
        }
    }
}
