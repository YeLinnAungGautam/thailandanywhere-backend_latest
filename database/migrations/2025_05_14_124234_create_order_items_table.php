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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // ဆက်စပ်မှုများ
            $table->unsignedBigInteger('product_id');
            $table->string('product_type'); // App\Models\Hotel, App\Models\EntranceTicket, etc.
            $table->unsignedBigInteger('variation_id')->nullable(); // EntranceTicket အမျိုးအစား
            $table->unsignedBigInteger('car_id')->nullable(); // ကားအမျိုးအစား
            $table->unsignedBigInteger('room_id')->nullable(); // အခန်းအမျိုးအစား

            // အချိန်နှင့်ရက်စွဲများ
            $table->date('checkin_date')->nullable(); // ဟိုတယ်ဝင်ရက်
            $table->date('checkout_date')->nullable(); // ဟိုတယ်ထွက်ရက်
            $table->date('service_date')->nullable(); // ဝန်ဆောင်မှုရက်

            // အရေအတွက်နှင့်စျေးနှုန်း
            $table->integer('quantity')->default(1);
            $table->decimal('cost_price', 10, 2)->nullable(); // ကုန်ကျစရိတ်
            $table->decimal('total_cost_price', 10, 2)->nullable(); // စုစုပေါင်းကုန်ကျစရိတ်
            $table->decimal('selling_price', 10, 2); // ရောင်းစျေး
            $table->decimal('total_selling_price', 10, 2); // စုစုပေါင်းရောင်းစျေး
            $table->decimal('discount', 10, 2)->default(0); // လျှော့စျေး

            // အခြားအချက်အလက်များ
            $table->text('special_request')->nullable(); // အထူးတောင်းဆိုချက်
            $table->text('route_plan')->nullable(); // ခရီးစဉ်
            $table->string('pickup_location')->nullable(); // ခရီးသည်ကြိုသည့်နေရာ
            $table->string('pickup_time')->nullable(); // ခရီးသည်ကြိုသည့်အချိန်

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
