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
        Schema::create('van_tour_cars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vantour_id');
            $table->unsignedBigInteger('car_id');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('agent_price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['vantour_id', 'car_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('van_tour_cars');
    }
};
