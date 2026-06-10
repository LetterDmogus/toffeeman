<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_payment_records_cash_paid_and_change_amount(): void
    {
        $user = User::factory()->create();

        // Create an unpaid order
        $order = Order::create([
            'order_number' => 'ORD-12345',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'tax_amount' => 5000,
            'final_amount' => 55000,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/orders/{$order->id}/pay", [
            'payment_method' => 'cash',
            'discount' => 5000,
            'cash_paid' => 100000,
            'change_amount' => 50000,
        ]);

        $response->assertStatus(200);

        // Verify order is paid
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(5000, $order->discount_amount);
        $this->assertEquals('cash', $order->payment_method);

        // Verify metadata has cash_paid and change_amount
        $this->assertArrayHasKey('cash_paid', $order->payment_metadata);
        $this->assertArrayHasKey('change_amount', $order->payment_metadata);
        $this->assertEquals(100000, $order->payment_metadata['cash_paid']);
        $this->assertEquals(50000, $order->payment_metadata['change_amount']);
    }

    public function test_cash_payment_validation_fails_for_invalid_amounts(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_number' => 'ORD-12346',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'tax_amount' => 5000,
            'final_amount' => 55000,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/orders/{$order->id}/pay", [
            'payment_method' => 'cash',
            'discount' => -100, // Invalid
            'cash_paid' => 'not-a-number', // Invalid
            'change_amount' => -50, // Invalid
        ]);

        $response->assertStatus(422);
    }
}
