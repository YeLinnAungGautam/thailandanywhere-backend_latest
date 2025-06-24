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
        Schema::table('reservation_booking_confirm_letters', function (Blueprint $table) {
            // Only add columns that don't exist
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'product_type')) {
                $table->string('product_type')->default('App\Models\Hotel');
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'company_legal_name')) {
                $table->string('company_legal_name')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'receipt_date')) {
                $table->date('receipt_date')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'service_start_date')) {
                $table->date('service_start_date')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'service_end_date')) {
                $table->date('service_end_date')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'receipt_image')) {
                $table->string('receipt_image')->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'total_tax_withold')) {
                $table->decimal('total_tax_withold', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'total_before_tax')) {
                $table->decimal('total_before_tax', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('reservation_booking_confirm_letters', 'total_after_tax')) {
                $table->decimal('total_after_tax', 10, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_booking_confirm_letters', function (Blueprint $table) {
            $columns = [
                'product_type', 'product_id', 'company_legal_name', 'receipt_date',
                'service_start_date', 'service_end_date', 'receipt_image',
                'total_tax_withold', 'total_before_tax', 'total_after_tax'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('reservation_booking_confirm_letters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
