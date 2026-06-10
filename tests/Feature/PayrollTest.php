<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPositionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('superadmin');

        $employeeUser = User::factory()->create(['status' => 'active']);
        $this->employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'salary' => 5_000_000,
            'status' => 'active',
        ]);
    }

    // ── Generate ─────────────────────────────────────────────────────────────

    public function test_admin_can_generate_payroll_slips(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/payroll/generate', [
                'month' => Carbon::now()->month,
                'year' => Carbon::now()->year,
            ]);

        $response->assertRedirect('/payroll');
        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $this->employee->id,
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_generate_payroll_slip_for_single_employee(): void
    {
        // Add another active employee
        $employeeUser2 = User::factory()->create(['status' => 'active']);
        $employee2 = Employee::factory()->create([
            'user_id' => $employeeUser2->id,
            'salary' => 6_000_000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/payroll/generate', [
                'month' => Carbon::now()->month,
                'year' => Carbon::now()->year,
                'employee_id' => $employee2->id,
            ]);

        $newPayroll = Payroll::where('employee_id', $employee2->id)->first();
        $response->assertRedirect("/payroll/{$newPayroll->id}");

        // Employee 2 should have a payroll, Employee 1 should not (since we only generated for 2)
        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee2->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('payrolls', [
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_generate_skips_duplicate_period(): void
    {
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;

        Payroll::create([
            'employee_id' => $this->employee->id,
            'period_month' => $month,
            'period_year' => $year,
            'base_salary' => 5_000_000,
            'working_days' => 26,
            'present_days' => 26,
            'absent_days' => 0,
            'gross_salary' => 5_000_000,
            'net_salary' => 5_000_000,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)->post('/payroll/generate', [
            'month' => $month,
            'year' => $year,
        ]);

        // Still only one payroll
        $this->assertEquals(1, Payroll::where('employee_id', $this->employee->id)->count());
    }

    // ── Recalculate ───────────────────────────────────────────────────────────

    public function test_recalculate_with_absence_deduction_active(): void
    {
        $payroll = new Payroll([
            'base_salary' => 5_000_000,
            'working_days' => 25,
            'present_days' => 23,
            'absent_days' => 2,
            'apply_absence_deduction' => true,
            'allowance' => 500_000,
            'deduction_other' => 100_000,
        ]);

        $payroll->recalculate();

        $expectedDeduction = round((5_000_000 / 25) * 2, 2); // 400,000
        $this->assertEquals($expectedDeduction, (float) $payroll->deduction_absence);
        $this->assertEquals(5_500_000, (float) $payroll->gross_salary);
        $this->assertEquals(5_500_000 - $expectedDeduction - 100_000, (float) $payroll->net_salary);
    }

    public function test_recalculate_without_absence_deduction(): void
    {
        $payroll = new Payroll([
            'base_salary' => 5_000_000,
            'working_days' => 25,
            'present_days' => 20,
            'absent_days' => 5,
            'apply_absence_deduction' => false,
            'allowance' => 0,
            'deduction_other' => 0,
        ]);

        $payroll->recalculate();

        $this->assertEquals(0, (float) $payroll->deduction_absence);
        $this->assertEquals(5_000_000, (float) $payroll->net_salary);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_draft_payroll(): void
    {
        $payroll = $this->createDraftPayroll();

        $this->actingAs($this->admin)
            ->patch("/payroll/{$payroll->id}", [
                'apply_absence_deduction' => false,
                'allowance' => 200_000,
                'allowance_notes' => 'Tunjangan makan',
                'deduction_other' => 50_000,
                'deduction_notes' => 'Bon koperasi',
                'bonus' => 100_000,
                'bonus_notes' => 'Kinerja bulanan',
                'shift_night_days' => 2,
                'shift_night_rate' => 50_000,
                'overtime_hours' => 5,
                'overtime_rate' => 20_000,
                'notes' => '',
            ])
            ->assertRedirect();

        $payroll->refresh();
        $this->assertEquals(200_000, (float) $payroll->allowance);
        $this->assertEquals(50_000, (float) $payroll->deduction_other);
        $this->assertEquals(100_000, (float) $payroll->bonus);
        $this->assertEquals(2, $payroll->shift_night_days);
        $this->assertEquals(50_000, (float) $payroll->shift_night_rate);
        $this->assertEquals(5, $payroll->overtime_hours);
        $this->assertEquals(20_000, (float) $payroll->overtime_rate);

        // Net salary calculation: base (5M) + allowance (200k) + bonus (100k) + shift pay (2*50k = 100k) + overtime (5*20k = 100k) - deduction_other (50k)
        // 5M + 200k + 100k + 100k + 100k - 50k = 5,450,000
        $this->assertEquals(5_450_000, (float) $payroll->net_salary);
    }

    public function test_cannot_update_approved_payroll(): void
    {
        $payroll = $this->createDraftPayroll(['status' => 'approved']);

        $this->actingAs($this->admin)
            ->patch("/payroll/{$payroll->id}", [
                'apply_absence_deduction' => false,
                'allowance' => 0,
                'allowance_notes' => '',
                'deduction_other' => 0,
                'deduction_notes' => '',
                'bonus' => 0,
                'bonus_notes' => '',
                'shift_night_days' => 0,
                'shift_night_rate' => 0,
                'overtime_hours' => 0,
                'overtime_rate' => 0,
                'notes' => '',
            ])
            ->assertRedirect();

        // Status unchanged, still approved
        $this->assertEquals('approved', $payroll->fresh()->status);
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function test_admin_can_approve_draft_payroll(): void
    {
        $payroll = $this->createDraftPayroll();

        $this->actingAs($this->admin)
            ->patch("/payroll/{$payroll->id}/approve")
            ->assertRedirect();

        $this->assertEquals('approved', $payroll->fresh()->status);
        $this->assertEquals($this->admin->id, $payroll->fresh()->approved_by);
    }

    // ── Pay ───────────────────────────────────────────────────────────────────

    public function test_admin_can_pay_approved_payroll_and_creates_transaction(): void
    {
        $payroll = $this->createDraftPayroll(['status' => 'approved', 'approved_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->patch("/payroll/{$payroll->id}/pay")
            ->assertRedirect();

        $payroll->refresh();
        $this->assertEquals('paid', $payroll->status);
        $this->assertNotNull($payroll->paid_at);
        $this->assertNotNull($payroll->transaction_id);

        $this->assertDatabaseHas('transactions', [
            'id' => $payroll->transaction_id,
            'type' => 'expense',
            'category' => 'salary',
        ]);
    }

    public function test_cannot_pay_draft_payroll(): void
    {
        $payroll = $this->createDraftPayroll();

        $this->actingAs($this->admin)
            ->patch("/payroll/{$payroll->id}/pay")
            ->assertRedirect();

        $this->assertEquals('draft', $payroll->fresh()->status);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_draft_payroll(): void
    {
        $payroll = $this->createDraftPayroll();

        $this->actingAs($this->admin)
            ->delete("/payroll/{$payroll->id}")
            ->assertRedirect('/payroll');

        $this->assertSoftDeleted($payroll);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftPayroll(array $overrides = []): Payroll
    {
        return Payroll::create(array_merge([
            'employee_id' => $this->employee->id,
            'period_month' => Carbon::now()->month,
            'period_year' => Carbon::now()->year,
            'base_salary' => 5_000_000,
            'working_days' => 26,
            'present_days' => 26,
            'absent_days' => 0,
            'apply_absence_deduction' => false,
            'allowance' => 0,
            'deduction_other' => 0,
            'gross_salary' => 5_000_000,
            'net_salary' => 5_000_000,
            'status' => 'draft',
        ], $overrides));
    }
}
