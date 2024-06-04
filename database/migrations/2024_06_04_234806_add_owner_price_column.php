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
        Schema::table('rooms', function (Blueprint $table) {
            $table->longText('owner_price')->nullable()->after('room_price');
        });

        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->longText('owner_price')->nullable()->after('cost_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('owner_price');
        });

        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->dropColumn('owner_price');
        });
    }
};
