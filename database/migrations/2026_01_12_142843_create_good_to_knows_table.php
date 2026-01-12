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
        Schema::create('good_to_knows', function (Blueprint $table) {
            $table->id();

            // Product နဲ့ ချိတ်ဆက်မှု (polymorphic)
            $table->morphs('knowable'); // knowable_id နဲ့ knowable_type

            // အချက်အလက်တွေ
            $table->string('title'); // "Check-in and Check-out"
            $table->text('description_mm'); // "Guests can check into their rooms..."
            $table->text('description_en'); // "Guests can check into their rooms..."

            // Icon သို့မဟုတ် Image
            $table->string('icon')->nullable(); // 'clock', 'shuttle', 'family' စတာတွေ

            // စီစဉ်မှု
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('good_to_knows');
    }
};
