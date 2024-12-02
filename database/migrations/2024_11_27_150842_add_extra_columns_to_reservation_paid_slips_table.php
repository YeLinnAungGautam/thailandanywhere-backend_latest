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
        Schema::table('reservation_paid_slips', function (Blueprint $table) {
            $table->after('file', function ($table) {
                $table->decimal('amount', 10, 2)->default(0);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_paid_slips', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};