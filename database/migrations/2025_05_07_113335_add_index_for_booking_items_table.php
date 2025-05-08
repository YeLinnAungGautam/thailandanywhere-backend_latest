<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            // $table->index(['booking_id', 'item_id', 'item_type'], 'booking_items_booking_id_item_id_item_type_index');
            $table->index(['product_type', 'product_id']);

            $table->index('car_id');
            $table->index('crm_id');
            $table->index('room_id');
            $table->index('hotel_id');
            $table->index('ticket_id');
            $table->index('booking_id');
            $table->index('variation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            // $table->dropIndex('booking_items_booking_id_item_id_item_type_index');
            $table->dropIndex(['product_type', 'product_id']);
            $table->dropIndex('booking_items_car_id_index');
            $table->dropIndex('booking_items_crm_id_index');
            $table->dropIndex('booking_items_room_id_index');
            $table->dropIndex('booking_items_hotel_id_index');
            $table->dropIndex('booking_items_ticket_id_index');
            $table->dropIndex('booking_items_booking_id_index');
            $table->dropIndex('booking_items_variation_id_index');
        });
    }
};
