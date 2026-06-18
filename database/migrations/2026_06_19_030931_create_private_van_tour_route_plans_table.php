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
        Schema::create('private_van_tour_route_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_van_tour_id')->constrained('private_van_tours')->cascadeOnDelete();
            $table->foreignId('route_plan_id')->constrained('route_plans')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn('vantour_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_van_tour_route_plans');
    }
};
