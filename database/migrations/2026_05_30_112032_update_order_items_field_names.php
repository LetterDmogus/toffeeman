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
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'item_id']);
            
            $table->foreignId('menu_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->after('menu_item_id')->constrained()->nullOnDelete();
            
            $table->renameColumn('base_price', 'price');
            $table->renameColumn('total_price', 'subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('item_type')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            
            $table->dropConstrainedForeignId('menu_item_id');
            $table->dropConstrainedForeignId('package_id');
            $table->renameColumn('price', 'base_price');
            $table->renameColumn('subtotal', 'total_price');
        });
    }
};
