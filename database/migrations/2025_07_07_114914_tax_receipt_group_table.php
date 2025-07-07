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
        Schema::create('tax_receipt_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_receipt_id')
                  ->constrained('tax_receipts')
                  ->onDelete('cascade');

            $table->foreignId('booking_item_group_id')
                  ->constrained('booking_item_groups')
                  ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_receipt_groups');
    }
};
