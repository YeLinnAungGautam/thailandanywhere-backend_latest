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
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->after('amount', function ($table) {
                $table->date('date')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('sender')->nullable();
                $table->boolean('is_corporate')->nullable();
            });
        });

        Schema::table('reservation_customer_passports', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('dob')->nullable();
        });

        Schema::table('reservation_booking_confirm_letters', function (Blueprint $table) {
            $table->after('file', function ($table) {
                $table->double('amount')->nullable();
                $table->string('invoice')->nullable();
                $table->date('due_date')->nullable();
                $table->string('customer')->nullable();
                $table->string('sender_name')->nullable();
            });
        });

        Schema::table('reservation_paid_slips', function (Blueprint $table) {
            $table->after('amount', function ($table) {
                $table->string('bank_name')->nullable();
                $table->date('date')->nullable();
                $table->boolean('is_corporate')->nullable();
                $table->string('comment')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dropColumn('bank_name');
            $table->dropColumn('sender');
            $table->dropColumn('is_corporate');
        });

        Schema::table('reservation_customer_passports', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('passport_number');
            $table->dropColumn('dob');
        });

        Schema::table('reservation_booking_confirm_letters', function (Blueprint $table) {
            $table->dropColumn('amount');
            $table->dropColumn('invoice');
            $table->dropColumn('due_date');
            $table->dropColumn('customer');
            $table->dropColumn('sender_name');
        });

        Schema::table('reservation_paid_slips', function (Blueprint $table) {
            $table->dropColumn('bank_name');
            $table->dropColumn('date');
            $table->dropColumn('is_corporate');
            $table->dropColumn('comment');
        });
    }
};
