<?php

namespace Tests\Feature;

use App\Models\GatewayCredential;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CajuPayPaidSessionBuffer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CajuPayPaidBufferRaceTest extends TestCase
{
    private const SESSION_ID = 'eebe016a-248c-4153-a032-1c7e84b5a46f';

    private const PAYMENT_ID = 'a1b2c3d4-e5f6-4789-a012-345678901234';

    private const WEBHOOK_SECRET = 'cwhsec_testsecret123456789012345678901234';

    private function seedCredential(): void
    {
        $cred = GatewayCredential::create([
            'tenant_id' => 1,
            'gateway_slug' => 'cajupay',
            'credentials' => '',
            'is_connected' => true,
        ]);
        $cred->setEncryptedCredentials([
            'public_key' => 'gpk_test',
            'secret_key' => 'gsk_test',
            'webhook_signing_secret' => self::WEBHOOK_SECRET,
        ]);
        $cred->save();
    }

    /**
     * @return array{raw: string, ts: string, sig: string}
     */
    private function signedPaidWebhook(): array
    {
        $payload = [
            'type' => 'checkout.payment.paid',
            'data' => [
                'object' => [
                    'gateway' => 'cajupay',
                    'checkout_session_id' => self::SESSION_ID,
                    'cajupay_charge_id' => self::PAYMENT_ID,
                    'payment_id' => self::PAYMENT_ID,
                    'amount_cents' => 9900,
                    'currency' => 'BRL',
                ],
            ],
        ];
        $raw = json_encode($payload);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$raw, self::WEBHOOK_SECRET);

        return compact('raw', 'ts', 'sig');
    }

    public function test_paid_webhook_before_order_is_buffered_and_applied_on_materialize(): void
    {
        Event::fake();
        $this->seedCredential();

        Http::fake([
            '*/api/sdk/public/checkout/sessions/*' => Http::response([
                'status' => 'active',
                'payment_status' => 'paid',
            ], 200),
            '*/api/payments*' => Http::response([], 200),
        ]);

        $signed = $this->signedPaidWebhook();
        $response = $this->call(
            'POST',
            route('webhooks.cajupay'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CAJUPAY_EVENT' => 'checkout.payment.paid',
                'HTTP_X_CAJUPAY_TIMESTAMP' => $signed['ts'],
                'HTTP_X_CAJUPAY_SIGNATURE' => 't='.$signed['ts'].',v1='.$signed['sig'],
            ],
            $signed['raw']
        );
        $response->assertOk();
        $response->assertJson(['received' => true]);

        $buffered = Cache::get(CajuPayPaidSessionBuffer::cacheKeyForSession(self::SESSION_ID));
        $this->assertIsArray($buffered);
        $this->assertSame(self::PAYMENT_ID, $buffered['payment_id'] ?? null);

        $user = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $product = $this->createTestProduct(['tenant_id' => 1, 'price' => 99]);
        $order = Order::create([
            'tenant_id' => 1,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 99,
            'currency' => 'BRL',
            'email' => $user->email,
            'gateway' => 'cajupay',
            'gateway_id' => self::SESSION_ID,
            'metadata' => [
                'cajupay_checkout_session_id' => self::SESSION_ID,
                'checkout_payment_method' => 'card',
            ],
        ]);

        $applied = CajuPayPaidSessionBuffer::applyToOrderIfBuffered($order);
        $this->assertTrue($applied);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(self::PAYMENT_ID, $order->metadata['cajupay_payment_id'] ?? null);
        // gateway_id permanece a sessão (lookup estável)
        $this->assertSame(self::SESSION_ID, $order->gateway_id);
    }

    public function test_paid_webhook_with_existing_order_completes_without_replacing_gateway_id(): void
    {
        Event::fake();
        $this->seedCredential();

        Http::fake([
            '*/api/sdk/public/checkout/sessions/*' => Http::response([
                'status' => 'active',
                'payment_status' => 'pending',
            ], 200),
            '*/api/payments*' => Http::response([], 200),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $product = $this->createTestProduct(['tenant_id' => 1, 'price' => 99]);
        $order = Order::create([
            'tenant_id' => 1,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'amount' => 99,
            'currency' => 'BRL',
            'email' => $user->email,
            'gateway' => 'cajupay',
            'gateway_id' => self::SESSION_ID,
            'metadata' => [
                'cajupay_checkout_session_id' => self::SESSION_ID,
                'checkout_payment_method' => 'card',
            ],
        ]);

        $signed = $this->signedPaidWebhook();
        $response = $this->call(
            'POST',
            route('webhooks.cajupay'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CAJUPAY_EVENT' => 'checkout.payment.paid',
                'HTTP_X_CAJUPAY_TIMESTAMP' => $signed['ts'],
                'HTTP_X_CAJUPAY_SIGNATURE' => 't='.$signed['ts'].',v1='.$signed['sig'],
            ],
            $signed['raw']
        );
        $response->assertOk();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(self::SESSION_ID, $order->gateway_id);
        $this->assertSame(self::PAYMENT_ID, $order->metadata['cajupay_payment_id'] ?? null);
    }
}
