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
        // Bookings table မှာ package link column ထည့်သည်
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('inclusive_package_id')
                  ->nullable()
                  ->after('is_inclusive');

            $table->foreign('inclusive_package_id')
                  ->references('id')
                  ->on('inclusive_packages')
                  ->onDelete('set null');
        });

        // Packages table မှာ clone ဆိုသည် သိမ်းသည်
        Schema::table('inclusive_packages', function (Blueprint $table) {
            $table->boolean('is_clone')
                  ->default(false)
                  ->after('status');

            $table->unsignedBigInteger('cloned_from_id')
                  ->nullable()
                  ->after('is_clone')
                  ->comment('Original template package ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['inclusive_package_id']);
            $table->dropColumn('inclusive_package_id');
        });

        Schema::table('inclusive_packages', function (Blueprint $table) {
            $table->dropColumn(['is_clone', 'cloned_from_id']);
        });
    }
};
