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
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('inclusive_description')->nullable()->after('inclusive_name');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('inclusive_description');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
