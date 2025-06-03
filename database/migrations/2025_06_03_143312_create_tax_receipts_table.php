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
        Schema::create('tax_receipts', function (Blueprint $table) {
            $table->id();
            // Polymorphic relationship fields
            $table->string('product_type'); // App\Models\Hotel, App\Models\EntranceTicket, etc.
            $table->unsignedBigInteger('product_id');
            $table->index(['product_type', 'product_id']);

            // Company and receipt details
            $table->string('company_legal_name');
            $table->date('receipt_date');
            $table->date('service_start_date');
            $table->date('service_end_date');
            $table->string('receipt_image')->nullable();

            // Note: reservation relationships are handled in separate pivot table
            // Keeping this field for backward compatibility or additional codes
            $table->json('additional_codes')->nullable();

            // Financial amounts (using decimal for precision)
            $table->decimal('total_tax_withold', 10, 2);
            $table->decimal('total_tax_amount', 10, 2);
            $table->decimal('total_after_tax', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_receipts');
    }
};
