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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // 'menu_item' or 'package'
            $table->unsignedBigInteger('item_id'); // ID of MenuItem or Package
            
            $table->string('name'); // Snapshot of item name
            $table->decimal('base_price', 12, 2);
            $table->integer('qty');
            
            // Complex Configuration via JSON
            $table->json('variants')->nullable(); // Array of chosen variants {option_name, value_name, additional_price}
            $table->json('add_ons')->nullable(); // Array of chosen add-ons {name, price, qty}
            $table->json('package_items')->nullable(); // For packages: Array of included items with their own variants/add-ons
            
            $table->decimal('total_price', 12, 2); // Calculated: (base_price + addons + variants) * qty
            $table->string('status')->default('pending'); // pending, cooking, done, served
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
