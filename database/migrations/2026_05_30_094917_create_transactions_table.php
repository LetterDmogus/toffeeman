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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->string('type'); // 'income' or 'expense'
            $table->string('category'); // 'order_sales', 'inventory_purchase', 'salary', 'maintenance', etc.
            
            // Polymorphic relation to link to Order, Employee, etc.
            $table->nullableMorphs('reference'); 
            
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // User who recorded the transaction
            
            $table->decimal('amount', 12, 2);
            $table->string('payment_method'); // 'cash', 'qris', 'transfer', etc.
            $table->string('payment_status')->default('completed'); // pending, completed, failed
            $table->text('description')->nullable();
            
            $table->timestamp('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
