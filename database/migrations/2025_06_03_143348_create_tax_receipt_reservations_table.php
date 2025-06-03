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
        Schema::create('tax_receipt_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_receipt_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_item_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure unique combination
            $table->unique(['tax_receipt_id', 'booking_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_receipt_reservations');
    }
};
