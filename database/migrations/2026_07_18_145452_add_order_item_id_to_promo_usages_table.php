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
        Schema::table('promo_usages', function (Blueprint $table) {
            $table->foreignId('order_item_id')
            ->nullable()
            ->after('promo_id')
            ->constrained('order_items')
            ->nullOnDelete();
        });
        Schema::table('promo_usages', function (Blueprint $table) {
            $table->foreignId('booking_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_usages', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropColumn('order_item_id');
        });
    }
};
