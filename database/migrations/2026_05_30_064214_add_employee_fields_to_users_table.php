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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->decimal('salary', 12, 2)->default(0.00)->after('password');
            $table->string('position')->default('waiter')->after('salary'); // manager, cashier, chef, waiter
            $table->string('role')->default('employee')->after('position'); // admin, employee
            $table->string('status')->default('active')->after('role'); // active, inactive, suspended
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'salary', 'position', 'role', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
