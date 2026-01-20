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
        Schema::table('booking_item_groups', function (Blueprint $table) {
            $table->text('comment_reserve')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_item_groups', function (Blueprint $table) {
            $table->dropColumn('comment_reserve');
        });
    }
};
