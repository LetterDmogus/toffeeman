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
            // Remove fields that are now in employees or handled by Spatie
            $table->dropColumn(['role', 'position', 'salary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('position')->default('waiter')->after('email');
            $table->string('role')->default('employee')->after('position');
            $table->decimal('salary', 12, 2)->default(0.00)->after('role');
        });
    }
};
