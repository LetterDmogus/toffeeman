<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPositionSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_can_manage_manual_transactions(): void
    {
        // 1. Store operational expense
        $response = $this->actingAs($this->admin)->postJson(route('api.transactions.store'), [
            'type' => 'expense',
            'category' => 'electricity',
            'amount' => 750000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'completed',
            'description' => 'Bayar listrik bulan ini.',
            'transaction_date' => '2026-06-10',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'category' => 'electricity',
            'amount' => 750000,
            'description' => 'Bayar listrik bulan ini.',
            'user_id' => $this->admin->id,
        ]);

        $transactionId = $response->json('id');

        // Verify creation audit log
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Transaction::class,
            'auditable_id' => $transactionId,
            'action' => 'create',
            'user_id' => $this->admin->id,
        ]);

        // 2. Read transactions list
        $listResponse = $this->actingAs($this->admin)->getJson(route('api.transactions.index', ['type' => 'expense']));
        $listResponse->assertStatus(200);
        $this->assertCount(1, $listResponse->json('data'));

        // 3. Update expense description or amount
        $updateResponse = $this->actingAs($this->admin)->patchJson(route('api.transactions.update', $transactionId), [
            'amount' => 800000,
            'description' => 'Bayar listrik bulan ini (ada penyesuaian biaya).',
        ]);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'amount' => 800000,
            'description' => 'Bayar listrik bulan ini (ada penyesuaian biaya).',
        ]);

        // Verify update audit log
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Transaction::class,
            'auditable_id' => $transactionId,
            'action' => 'update',
            'user_id' => $this->admin->id,
        ]);

        // 4. Delete manual transaction
        $this->actingAs($this->admin)->deleteJson(route('api.transactions.destroy', $transactionId))
            ->assertStatus(200);

        $this->assertSoftDeleted('transactions', [
            'id' => $transactionId,
        ]);

        // Verify deletion audit log
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Transaction::class,
            'auditable_id' => $transactionId,
            'action' => 'delete',
            'user_id' => $this->admin->id,
        ]);
    }
}
