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
        Schema::table('inclusives', function (Blueprint $table) {
            $table->longText('product_itenary_material')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inclusives', function (Blueprint $table) {
            $table->dropColumn('product_itenary_material');
        });
    }
};
