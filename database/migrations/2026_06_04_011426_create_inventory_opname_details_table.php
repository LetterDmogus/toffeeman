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
        Schema::create('inventory_opname_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_opname_id')->constrained('inventory_opnames')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('qty_good_system', 10, 2)->default(0.00);
            $table->decimal('qty_good_physical', 10, 2)->default(0.00);
            $table->decimal('qty_fair_system', 10, 2)->default(0.00);
            $table->decimal('qty_fair_physical', 10, 2)->default(0.00);
            $table->decimal('qty_damaged_system', 10, 2)->default(0.00);
            $table->decimal('qty_damaged_physical', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_opname_details');
    }
};
