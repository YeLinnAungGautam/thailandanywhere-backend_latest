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
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('id');
            $table->date('end_date')->nullable()->after('start_date');

            // Add indexes for better performance
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['bookings_start_date_index']);
            $table->dropIndex(['bookings_end_date_index']);
            $table->dropIndex(['bookings_start_date_end_date_index']);
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
