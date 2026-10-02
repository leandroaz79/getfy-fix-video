<?php

namespace Tests\Unit;

use App\Gateways\CajuPay\CajuPayDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CajuPaySdkSessionStatusTest extends TestCase
{
    public function test_prefers_payment_status_paid_over_session_status_active(): void
    {
        Http::fake([
            '*/api/sdk/public/checkout/sessions/*' => Http::response([
                'checkout_session_id' => 'sess-1',
                'status' => 'active',
                'payment_status' => 'paid',
            ], 200),
        ]);

        $driver = new CajuPayDriver;
        $this->assertSame('paid', $driver->getSdkSessionStatus('tok_public_test'));
    }

    public function test_maps_active_session_with_pending_payment_as_pending(): void
    {
        Http::fake([
            '*/api/sdk/public/checkout/sessions/*' => Http::response([
                'status' => 'active',
                'payment_status' => 'pending',
            ], 200),
        ]);

        $driver = new CajuPayDriver;
        $this->assertSame('pending', $driver->getSdkSessionStatus('tok_public_test'));
    }

    public function test_nested_payment_status_when_top_level_is_only_active(): void
    {
        Http::fake([
            '*/api/sdk/public/checkout/sessions/*' => Http::response([
                'status' => 'active',
                'payment' => ['status' => 'succeeded'],
            ], 200),
        ]);

        $driver = new CajuPayDriver;
        $this->assertSame('paid', $driver->getSdkSessionStatus('tok_public_test'));
    }
}
