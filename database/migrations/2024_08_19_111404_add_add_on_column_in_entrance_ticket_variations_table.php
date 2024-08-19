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
            $table->boolean('is_add_on')->default('0')->nullable();
            $table->integer('add_on_price')->default('0')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->dropColumn(['is_add_on', 'add_on_price']);
        });
    }
};
