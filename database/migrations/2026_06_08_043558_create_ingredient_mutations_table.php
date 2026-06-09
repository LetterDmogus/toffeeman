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
        Schema::create('ingredient_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'in', 'out'
            $table->decimal('qty', 12, 2); // in base/small unit
            $table->nullableMorphs('reference');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_mutations');
    }
};
