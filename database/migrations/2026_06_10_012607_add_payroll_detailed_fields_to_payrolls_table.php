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
        Schema::table('payrolls', function (Blueprint $table) {
            // Bonus fields
            $table->decimal('bonus', 12, 2)->default(0)->after('allowance_notes');
            $table->string('bonus_notes')->nullable()->after('bonus');

            // Schedule / Shift / Overtime influences
            $table->unsignedSmallInteger('shift_night_days')->default(0)->after('bonus_notes');
            $table->decimal('shift_night_rate', 12, 2)->default(0)->after('shift_night_days');
            $table->unsignedSmallInteger('overtime_hours')->default(0)->after('shift_night_rate');
            $table->decimal('overtime_rate', 12, 2)->default(0)->after('overtime_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'bonus',
                'bonus_notes',
                'shift_night_days',
                'shift_night_rate',
                'overtime_hours',
                'overtime_rate',
            ]);
        });
    }
};
