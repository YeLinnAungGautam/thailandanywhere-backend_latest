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
        Schema::table('room_periods', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('agent_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_periods', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
