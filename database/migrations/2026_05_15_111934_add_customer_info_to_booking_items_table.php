<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->string('car_customer_contact')->nullable();
            $table->decimal('car_total_collect', 10, 2)->nullable();
            $table->string('car_payment_method')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['car_customer_contact', 'car_total_collect', 'car_payment_method']);
        });
    }
};
