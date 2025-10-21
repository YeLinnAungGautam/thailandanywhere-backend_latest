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
            $table->after('rating', function (Blueprint $table) {
                $table->string('latitude')->nullable();
                $table->string('longitude')->nullable();
            });
        });

        Schema::table('places', function (Blueprint $table) {
            $table->after('address', function (Blueprint $table) {
                $table->string('latitude')->nullable();
                $table->string('longitude')->nullable();
                $table->string('radius_km')->nullable();
            });
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->after('image', function (Blueprint $table) {
                $table->string('latitude')->nullable();
                $table->string('longitude')->nullable();
                $table->string('radius_km')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_km']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_km']);
        });
    }
};
