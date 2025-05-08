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
            $table->enum('verify_status', ['verified', 'unverified', 'pending'])
                  ->default('pending');

            // Add an index for better query performance
            $table->index('verify_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
             $table->dropIndex(['verify_status']);
            $table->dropColumn('verify_status');
        });
    }
};
