<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Period
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->unsignedSmallInteger('period_year');

            // Salary components
            $table->decimal('base_salary', 12, 2)->default(0);

            // Attendance summary
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);

            // Absence deduction toggle
            $table->boolean('apply_absence_deduction')->default(false);
            $table->decimal('deduction_absence', 12, 2)->default(0);

            // Manual adjustments
            $table->decimal('allowance', 12, 2)->default(0);
            $table->string('allowance_notes')->nullable();
            $table->decimal('deduction_other', 12, 2)->default(0);
            $table->string('deduction_notes')->nullable();

            // Computed totals (stored for historical accuracy)
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);

            // Workflow
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint — one slip per employee per period
            $table->unique(['employee_id', 'period_month', 'period_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
