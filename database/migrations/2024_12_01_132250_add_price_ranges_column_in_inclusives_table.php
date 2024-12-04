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
        Schema::table('inclusives', function (Blueprint $table) {
            $table->text('price_range')->nullable()->after('agent_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inclusives', function (Blueprint $table) {
            $table->dropColumn('price_range');
        });
    }
};
