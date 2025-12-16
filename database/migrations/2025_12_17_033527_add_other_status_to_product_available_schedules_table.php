<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the new 'other' option to status enum
        DB::statement("ALTER TABLE product_available_schedules MODIFY COLUMN status ENUM('pending', 'available', 'unavailable', 'other') DEFAULT 'pending'");

        // Add res_comment column
        Schema::table('product_available_schedules', function (Blueprint $table) {
            $table->longText('res_comment')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         // Remove res_comment column
        Schema::table('product_available_schedules', function (Blueprint $table) {
            $table->dropColumn('res_comment');
        });

        // Remove 'other' from status enum
        DB::statement("ALTER TABLE product_available_schedules MODIFY COLUMN status ENUM('pending', 'available', 'unavailable') DEFAULT 'pending'");
    }
};
