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
        Schema::table('tax_receipts', function (Blueprint $table) {
            $table->boolean('declaration')->default(false)->nullable();
            $table->boolean('complete_print')->default(false)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_receipts', function (Blueprint $table) {
            $table->dropColumn('declaration');
            $table->dropColumn('complete_print');
        });
    }
};
