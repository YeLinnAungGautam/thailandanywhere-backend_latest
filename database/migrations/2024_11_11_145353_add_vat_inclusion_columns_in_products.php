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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('vat_inclusion')->nullable();
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->string('vat_inclusion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('vat_inclusion');
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->dropColumn('vat_inclusion');
        });
    }
};
