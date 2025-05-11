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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->string('product_type'); // App\Models\Hotel, App\Models\EntranceTicket, etc.
            $table->unsignedBigInteger('variation_id')->nullable(); // room_id, car_id, etc.
            $table->integer('quantity')->default(1);
            $table->date('service_date')->nullable(); // For tours/tickets
            $table->date('checkout_date')->nullable(); // For hotels
            $table->json('options')->nullable(); // Additional options
            $table->timestamps();

            $table->index(['product_id', 'product_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
