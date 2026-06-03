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
            $table->date('purchase_date')->nullable();
            $table->decimal('qty_good', 10, 2)->default(0.00);
            $table->decimal('qty_fair', 10, 2)->default(0.00);
            $table->decimal('qty_damaged', 10, 2)->default(0.00);
            $table->string('storage_location')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_date', 'qty_good', 'qty_fair', 'qty_damaged', 'storage_location']);
        });
    }
};
