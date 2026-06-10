<?php

namespace App\Models;

use Database\Factories\PayrollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    /** @use HasFactory<PayrollFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'deduction_absence' => 'decimal:2',
        'allowance' => 'decimal:2',
        'deduction_other' => 'decimal:2',
        'bonus' => 'decimal:2',
        'shift_night_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'apply_absence_deduction' => 'boolean',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the employee that owns this payroll.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who approved this payroll.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the transaction linked to this payroll.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Recalculate and persist all computed totals.
     */
    public function recalculate(): void
    {
        $absenceDeduction = $this->apply_absence_deduction && $this->working_days > 0
            ? round(($this->base_salary / $this->working_days) * $this->absent_days, 2)
            : 0;

        $shiftNightPay = round($this->shift_night_days * $this->shift_night_rate, 2);
        $overtimePay = round($this->overtime_hours * $this->overtime_rate, 2);

        $gross = $this->base_salary + $this->allowance + $this->bonus + $shiftNightPay + $overtimePay;
        $net = $gross - $absenceDeduction - $this->deduction_other;

        $this->deduction_absence = $absenceDeduction;
        $this->gross_salary = $gross;
        $this->net_salary = max(0, $net);
    }
}
