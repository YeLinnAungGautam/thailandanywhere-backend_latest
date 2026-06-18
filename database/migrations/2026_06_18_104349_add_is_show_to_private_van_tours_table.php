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
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->boolean('is_show')->default(true)->after('ticket_price');
            $table->json('supplier_cost')->nullable()->after('is_show');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('private_van_tours', function (Blueprint $table) {
            $table->dropColumn(['is_show', 'supplier_cost']);
        });
    }
};
