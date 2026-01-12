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
        Schema::create('key_highlights', function (Blueprint $table) {
            $table->id();

            // Product ဘယ်ဟာနဲ့ ချိတ်သလဲဆိုတာ (polymorphic relationship အတွက်)
            $table->morphs('highlightable'); // highlightable_id နဲ့ highlightable_type ဆိုပြီး column ၂ ခု create လုပ်မယ်
            // highlightable_type = 'App\Models\Hotel' or 'App\Models\Attraction'
            // highlightable_id = hotel_id or attraction_id

            // Highlight အချက်အလက်တွေ
            $table->string('title'); // "Central Retail Hub"
            $table->text('description_mm'); // "Ideally located near MBK Center..."
            $table->text('description_en'); // "Ideally located near MBK Center..."

            // ပုံတွေ
            $table->string('image_url')->nullable(); // highlight ရဲ့ ပုံ

            // စီစဉ်မှု
            $table->integer('order')->default(0); // display လုပ်မယ့် အစီအစဉ်
            $table->boolean('is_active')->default(true); // ပြသမလား မပြသဘူးလား

            $table->timestamps();

            // Indexes for better performance
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('key_highlights');
    }
};
