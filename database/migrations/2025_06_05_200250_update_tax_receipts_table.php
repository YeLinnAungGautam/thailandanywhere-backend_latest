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
        Schema::table('tax_receipts', function (Blueprint $table) {
            // Add invoice_number column
            $table->string('invoice_number')->after('company_legal_name');

            // Drop the total column
            $table->dropColumn('total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_receipts', function (Blueprint $table) {
            // Add back the total column
            $table->decimal('total', 10, 2)->after('total_after_tax');

            // Drop the invoice_number column
            $table->dropColumn('invoice_number');

        });
    }
};
