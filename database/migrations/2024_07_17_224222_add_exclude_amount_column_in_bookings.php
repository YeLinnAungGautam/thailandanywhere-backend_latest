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
            $table->integer('exclude_amount')->nullable()->after('grand_total');
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->boolean('is_excluded')->nullable()->default(false)->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['exclude_amount']);
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['is_excluded']);
        });
    }
};
