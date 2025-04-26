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
            $table->boolean('is_driver_collect')->nullable()->default(null)->change();
            $table->string('contact_number')->nullable();
            $table->text('collect_comment')->nullable();
            $table->integer('total_pax')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'collect_comment', 'total_pax']);
        });

    }
};
