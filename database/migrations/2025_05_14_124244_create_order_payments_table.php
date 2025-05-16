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
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            $table->decimal('amount', 10, 2); // ငွေပမာဏ
            $table->string('payment_method'); // ငွေပေးချေမှုနည်းလမ်း
            $table->string('payment_slip')->nullable(); // ပြေစာပုံ
            $table->timestamp('payment_date'); // ငွေပေးချေသည့်ရက်

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('admins'); // အတည်ပြုသူ

            $table->timestamps();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
