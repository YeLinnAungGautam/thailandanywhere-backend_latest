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
        Schema::create('van_tour_cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vantour_id');
            $table->unsignedBigInteger('city_id');
            $table->timestamps();

            $table->unique(['vantour_id', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('van_tour_cities');
    }
};
