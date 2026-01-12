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
        Schema::create('near_by_places', function (Blueprint $table) {
            $table->id();

                // Product နး့ ချိတ်ဆက်မှု (Hotel or Attraction)
                $table->morphs('placeable'); // placeable_id နဲ့ placeable_type

                // Category - အဓိက အမျိုးအစား
                $table->enum('category', ['transport', 'landmarks', 'essentials', 'others'])
                    ->default('others');

                // Sub-category - အသေးစိတ် အမျိုးအစား
                // Transport: train, bus, airport, taxi, boat
                // Landmarks: temple, museum, monument, park, beach
                // Essentials: shopping, hospital, bank, pharmacy, police
                // Others: restaurant, cafe, bar, spa, gym
                $table->string('sub_category')->nullable();

                // Basic Information
                $table->string('name'); // "National Stadium Station (BTS)"

                // Distance Information
                $table->string('distance'); // "1.5 km" or "400 m" - ပြသဖို့
                $table->decimal('distance_value', 10, 2); // 1.5, 0.4 - sorting အတွက်
                $table->enum('distance_unit', ['m', 'km', 'mi'])->default('km');

                // Time Information
                $table->integer('walking_time')->nullable(); // minutes
                $table->integer('driving_time')->nullable(); // minutes

                // Visual
                $table->string('icon')->nullable(); // Icon name

                // Display Settings
                $table->integer('order')->default(0); // စီစဉ်မှု
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                // Indexes for Performance
                $table->index('category');
                $table->index('sub_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('near_by_places');
    }
};
