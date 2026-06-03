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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique()->nullable();
            $table->foreignId('ingredient_category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 10, 2)->default(0.00);
            $table->string('unit')->default('pcs');
            $table->decimal('min_qty', 10, 2)->default(0.00);
            $table->string('status')->default('in_stock'); // in_stock, low_stock, out_of_stock
            $table->decimal('price', 10, 2)->default(0.00);
            $table->date('expiration_date')->nullable();
            $table->string('storage_temperature')->nullable();
            $table->string('batch_number')->nullable();
            $table->decimal('small_unit_qty', 10, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
