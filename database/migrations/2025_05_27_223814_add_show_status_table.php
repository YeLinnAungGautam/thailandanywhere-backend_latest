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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('app_show_status')->nullable()->default(null)
                  ->comment('upcoming, completed, cancelled');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('app_show_status')->nullable()->default(null)
                  ->comment('upcoming, ongoing, completed, cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('app_show_status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('app_show_status');
        });
    }
};
