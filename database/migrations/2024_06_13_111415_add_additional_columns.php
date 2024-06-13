<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('location_map_title')->nullable();
            $table->longText('location_map')->nullable();
            $table->longText('nearby_places')->nullable();
            $table->integer('rating')->nullable();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->longText('amenities')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['location_map_title', 'location_map', 'nearby_places', 'rating']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('amenities');
        });
    }
};
