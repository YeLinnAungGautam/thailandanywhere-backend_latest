<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->string('currency')->default('THB')->nullable();
        });

        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->string('currency')->default('THB')->nullable();
        });

        // Update existing records to THB
        DB::table('booking_receipts')->whereNull('currency')->update(['currency' => 'THB']);
        DB::table('reservation_expense_receipts')->whereNull('currency')->update(['currency' => 'THB']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
