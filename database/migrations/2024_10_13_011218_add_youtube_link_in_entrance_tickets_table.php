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
        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->string('location_map_title')->nullable();
            $table->longText('location_map')->nullable();
            $table->longText('youtube_link')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->dropColumn(['location_map_title', 'location_map', 'youtube_link']);
        });
    }
};
