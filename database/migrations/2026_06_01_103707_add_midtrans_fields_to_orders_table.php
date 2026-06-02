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
            $table->text('qr_url')->nullable()->after('payment_status');
            $table->string('va_number')->nullable()->after('qr_url');
            $table->string('va_bank')->nullable()->after('va_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['qr_url', 'va_number', 'va_bank']);
        });
    }
};
