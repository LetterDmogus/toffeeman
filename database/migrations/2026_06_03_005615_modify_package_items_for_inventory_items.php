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
        Schema::table('package_items', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable()->change();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
