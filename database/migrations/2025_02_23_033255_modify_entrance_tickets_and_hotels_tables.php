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
        // Modify the `entrance_tickets` table
        Schema::table('entrance_tickets', function (Blueprint $table) {
            // Change the `email` column type from string to longText
            $table->longText('email')->nullable()->change();

            // Add a new column (e.g., contract_name)
            $table->string('contract_name')->nullable()->after('email');
        });

        // Modify the `hotels` table
        Schema::table('hotels', function (Blueprint $table) {
            // Change the `email` column type from string to longText
            $table->longText('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes for the `entrance_tickets` table
        Schema::table('entrance_tickets', function (Blueprint $table) {
            // Revert the `email` column type back to string
            $table->string('email')->nullable()->change();

            // Drop the new column (e.g., contract_name)
            $table->dropColumn('contract_name)');
        });

        // Revert changes for the `hotels` table
        Schema::table('hotels', function (Blueprint $table) {
            // Revert the `email` column type back to string
            $table->string('email')->nullable()->change();
        });
    }
};
