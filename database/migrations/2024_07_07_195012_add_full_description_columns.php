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
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->longText('full_description_en')->after('full_description')->nullable();
        });

        Schema::table('group_tours', function (Blueprint $table) {
            $table->longText('full_description_en')->after('full_description')->nullable();
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->longText('full_description_en')->after('full_description')->nullable();
        });

        Schema::table('airlines', function (Blueprint $table) {
            $table->longText('full_description_en')->after('full_description')->nullable();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->longText('full_description_en')->after('full_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->dropColumn('full_description_en');
        });

        Schema::table('group_tours', function (Blueprint $table) {
            $table->dropColumn('full_description_en');
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->dropColumn('full_description_en');
        });

        Schema::table('airlines', function (Blueprint $table) {
            $table->dropColumn('full_description_en');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('full_description_en');
        });
    }
};
