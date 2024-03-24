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
        Schema::create('booking_item_reservation_transaction', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_item_id');
            $table->unsignedBigInteger('reservation_transaction_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_item_reservation_transaction');
    }
};
