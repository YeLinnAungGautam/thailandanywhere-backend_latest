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
        Schema::table('private_van_tour_cars', function (Blueprint $table) {
            $table->decimal('cost', 8, 2)->after('price')->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('private_van_tour_cars', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
