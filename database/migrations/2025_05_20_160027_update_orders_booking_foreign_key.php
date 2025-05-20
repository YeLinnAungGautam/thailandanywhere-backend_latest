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
            // Drop the existing foreign key constraint
            $table->dropForeign(['booking_id']);

            // Re-add the foreign key with onDelete('set null')
            $table->foreign('booking_id')
                  ->references('id')
                  ->on('bookings')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the modified foreign key
            $table->dropForeign(['booking_id']);

            // Restore original foreign key constraint
            $table->foreign('booking_id')
                  ->references('id')
                  ->on('bookings');
        });

    }
};
