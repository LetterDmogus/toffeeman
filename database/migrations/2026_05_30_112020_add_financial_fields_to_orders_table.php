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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_amount', 12, 2)->after('order_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            $table->decimal('final_amount', 12, 2)->after('tax_amount');
            $table->string('payment_method')->nullable()->after('final_amount');
            $table->string('payment_status')->default('pending')->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'total_amount',
                'discount_amount',
                'tax_amount',
                'final_amount',
                'payment_method',
                'payment_status',
            ]);
        });
    }
};
