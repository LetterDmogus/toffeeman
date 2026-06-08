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
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            // Condition (trigger) side
            $table->string('condition_type')->default('min_amount'); // min_amount | min_qty | specific_item_qty
            $table->decimal('condition_value', 10, 2)->nullable();
            $table->foreignId('condition_menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();

            // Reward (action) side
            $table->string('reward_type')->default('discount_percent'); // discount_percent | discount_nominal | free_item
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->foreignId('reward_menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('reward_scope')->default('all'); // all | specific

            // Schedule
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('schedule_days')->nullable(); // [0,1,2,3,4,5,6] where 0=Sunday

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
