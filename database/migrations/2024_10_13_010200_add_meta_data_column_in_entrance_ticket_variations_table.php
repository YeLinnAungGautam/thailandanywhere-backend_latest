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
        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->longText('meta_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->dropColumn('meta_data');
        });
    }
};
