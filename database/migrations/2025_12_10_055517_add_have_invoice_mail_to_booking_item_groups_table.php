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
            $table->boolean('have_invoice_mail')->default(0);
            $table->date('invoice_mail_sent_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_item_groups', function (Blueprint $table) {
            $table->dropColumn('have_invoice_mail');
            $table->dropColumn('invoice_mail_sent_date');
        });
    }
};
