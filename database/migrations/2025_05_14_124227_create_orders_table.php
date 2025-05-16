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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('admin_id')->nullable()->constrained('admins');

            $table->string('order_number')->unique();
            $table->string('sold_from')->nullable();
            $table->string('phone_number');
            $table->string('email');

            $table->timestamp('order_datetime');
            $table->timestamp('expire_datetime');
            $table->date('balance_due_date')->nullable();

            $table->enum('order_status', ['pending', 'processing', 'confirmed', 'cancelled'])->default('pending');
            $table->foreignId('booking_id')->nullable()->constrained('bookings');

            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('sub_total', 10, 2);
            $table->decimal('grand_total',10,2);
            $table->decimal('deposit_amount', 10, 2)->nullable();

            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('admin_id');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
