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
        Schema::create('inventory_out_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_out_id')->constrained('inventory_outs')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('qty_good', 10, 2)->default(0.00);
            $table->decimal('qty_fair', 10, 2)->default(0.00);
            $table->decimal('qty_damaged', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_out_details');
    }
};
