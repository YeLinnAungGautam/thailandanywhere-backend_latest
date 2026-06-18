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
        Schema::create('route_plans', function (Blueprint $table) {
            $table->id();
            $table->json('vantour_ids')->nullable();
            $table->json('destination_ids')->nullable();
            $table->json('city_ids')->nullable();

            $table->string('main_cover_photo')->nullable();
            $table->json('other_photos')->nullable();

            $table->text('english_description')->nullable();
            $table->text('mm_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_plans');
    }
};
