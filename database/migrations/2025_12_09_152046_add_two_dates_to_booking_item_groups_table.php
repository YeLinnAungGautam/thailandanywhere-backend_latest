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
        Schema::table('booking_item_groups', function (Blueprint $table) {
            $table->date('booking_email_sent_date')->nullable();
            $table->date('expense_email_sent_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_item_groups', function (Blueprint $table) {
            $table->dropColumn('booking_email_sent_date');
            $table->dropColumn('expense_email_sent_date');
        });
    }
};
