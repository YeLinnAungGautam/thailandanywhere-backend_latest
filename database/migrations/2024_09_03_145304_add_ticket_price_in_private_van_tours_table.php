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
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->boolean('with_ticket')->default(false);
            $table->decimal('ticket_price', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->dropColumn(['with_ticket', 'ticket_price']);
        });
    }
};
