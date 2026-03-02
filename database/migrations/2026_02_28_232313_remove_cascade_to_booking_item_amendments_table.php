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
        Schema::table('booking_item_amendments', function (Blueprint $table) {
            // Drop existing foreign key constraint first
            $table->dropForeign(['booking_item_id']);

            // Make column nullable
            $table->unsignedBigInteger('booking_item_id')->nullable()->change();

            // Re-add foreign key with nullOnDelete
            $table->foreign('booking_item_id')->references('id')->on('booking_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_item_amendments', function (Blueprint $table) {
            $table->dropForeign(['booking_item_id']);
            $table->unsignedBigInteger('booking_item_id')->nullable(false)->change();
            $table->foreign('booking_item_id')->references('id')->on('booking_items')->cascadeOnDelete();
        });
    }
};
