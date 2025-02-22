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
        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(); // Decimal column for amount (10 digits total, 2 decimal places)
            $table->string('bank_name')->nullable(); // String column for bank name (nullable)
            $table->date('date')->nullable(); // Date column for date (format: YYYY-MM-DD)
            $table->boolean('is_corporate')->default(0); // Boolean column for is_corporate (default: 0)
            $table->string('comment')->nullable(); // Text column for comment (nullable)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->dropColumn(['amount', 'bank_name', 'date', 'is_corporate', 'comment']); // Drop all the added columns
        });
    }
};
