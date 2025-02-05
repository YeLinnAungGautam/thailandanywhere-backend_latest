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
        Schema::table('restaurant_inclusive_info', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['inclusive_id']);

            // Rename column
            $table->renameColumn('inclusive_id', 'inclusive_detail_id');

            // Add new foreign key
            $table->foreign('inclusive_detail_id')->references('id')->on('inclusive_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_inclusive_info', function (Blueprint $table) {
            $table->dropForeign(['inclusive_detail_id']);

            // Rename column back
            $table->renameColumn('inclusive_detail_id', 'inclusive_id');

            // Add back the original foreign key
            $table->foreign('inclusive_id')->references('id')->on('inclusive_details')->onDelete('cascade');
        });
    }
};
