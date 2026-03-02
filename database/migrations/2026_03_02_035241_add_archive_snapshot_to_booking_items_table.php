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
        Schema::table('booking_items', function (Blueprint $table) {
            $table->json('archive_snapshot')->nullable()->after('addon');
            // Product detail snapshot
            $table->json('product_snapshot')->nullable()->after('archive_snapshot');
            // Variation/Room/Car/Ticket snapshot
            $table->json('variation_snapshot')->nullable()->after('product_snapshot');
            // Price snapshot at time of booking
            $table->json('price_snapshot')->nullable()->after('variation_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['archive_snapshot', 'product_snapshot', 'variation_snapshot', 'price_snapshot']);
        });
    }
};
