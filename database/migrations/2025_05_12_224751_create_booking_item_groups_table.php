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
        Schema::create('booking_item_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            $table->string('product_type');

            $table->double('total_cost_price')->nullable();

            $table->string('invoice_sender')->nullable();
            $table->string('invoice_date')->nullable();
            $table->string('invoice_due_date')->nullable();
            $table->double('invoice_amount')->nullable();

            $table->boolean('sent_booking_request')->default(false);
            $table->string('booking_request_proof')->nullable()->comment('File name for Booking request proof');

            $table->json('passport_info')->nullable()->comment('Passport info for all booking item');

            $table->string('expense_method')->nullable()->comment('Expense method for all booking item');
            $table->string('expense_status')->nullable()->comment('Expense status for all booking item');
            $table->string('expense_bank_name')->nullable();
            $table->string('expense_bank_account')->nullable();

            $table->boolean('sent_expense_mail')->default(false);
            $table->string('expense_mail_proof')->nullable()->comment('File name for Booking request proof');

            $table->double('expense_total_amount')->nullable();

            $table->string('confirmation_status')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->string('confirmation_image')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_item_groups');
    }
};
