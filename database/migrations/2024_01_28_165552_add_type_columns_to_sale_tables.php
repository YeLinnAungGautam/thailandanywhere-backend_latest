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
            $table->enum('type', ['direct_booking', 'other_booking'])->default('other_booking')->after('description');
        });

        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->enum('type', ['van_tour', 'car_rental'])->default('car_rental')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
