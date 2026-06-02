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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // Relates to categories table
            $table->string('status')->default('available'); // available, sold_out, draft
            $table->string('image_url')->nullable();
            $table->json('allergens')->nullable(); // e.g. ["nuts", "dairy", "gluten"]
            $table->unsignedInteger('calories')->nullable();
            $table->unsignedInteger('preparation_time')->nullable(); // in minutes
            $table->json('tags')->nullable(); // e.g. ["bestseller", "signature", "vegan", "vegetarian"]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
