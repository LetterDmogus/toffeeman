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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'expiration_date']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();
        });
    }
};
