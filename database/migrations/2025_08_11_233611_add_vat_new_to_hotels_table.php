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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('vat_id')->nullable();
            $table->string('vat_name')->nullable();
            $table->string('vat_address')->nullable();
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->string('vat_id')->nullable();
            $table->string('vat_name')->nullable();
            $table->string('vat_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('vat_id');
            $table->dropColumn('vat_name');
            $table->dropColumn('vat_address');
        });

        Schema::table('entrance_tickets', function (Blueprint $table) {
            $table->dropColumn('vat_id');
            $table->dropColumn('vat_name');
            $table->dropColumn('vat_address');
        });
    }
};
