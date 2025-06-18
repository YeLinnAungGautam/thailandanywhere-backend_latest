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
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->string('reciever')->nullable();
            $table->string('interact_bank')->nullable();
        });
        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->string('reciever')->nullable();
            $table->string('sender')->nullable();
            $table->string('interact_bank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_receipts', function (Blueprint $table) {
            $table->dropColumn('reciever');
            $table->dropColumn('interact_bank');
        });
        Schema::table('reservation_expense_receipts', function (Blueprint $table) {
            $table->dropColumn('reciever');
            $table->dropColumn('sender');
            $table->dropColumn('interact_bank');
        });
    }
};
