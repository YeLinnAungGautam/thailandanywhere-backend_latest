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
        Schema::table('booking_items', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['verify_status']);
            // Then drop the column
            $table->dropColumn('verify_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->enum('verify_status', ['verified', 'unverified', 'pending'])
                  ->default('pending');

            // Re-add the index
            $table->index('verify_status');
        });
    }
};
