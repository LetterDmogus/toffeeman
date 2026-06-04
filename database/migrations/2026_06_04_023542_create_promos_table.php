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
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('type'); // 'discount_percent', 'discount_nominal', 'bogo'
            $table->decimal('value', 10, 2)->nullable(); // e.g. 10.00 for 10% or nominal
            $table->decimal('min_order_amount', 10, 2)->default(0.00);
            $table->integer('buy_qty')->nullable();
            $table->integer('get_qty')->nullable();
            $table->foreignId('buy_menu_item_id')->nullable()->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('get_menu_item_id')->nullable()->constrained('menu_items')->onDelete('cascade');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
