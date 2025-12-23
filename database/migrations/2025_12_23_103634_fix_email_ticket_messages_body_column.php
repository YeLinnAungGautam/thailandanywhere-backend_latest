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
        Schema::table('email_ticket_messages', function (Blueprint $table) {
            // Change body column to longText to handle large email content
            $table->longText('body')->nullable()->change();

            // Also ensure from and to fields can handle email addresses with names
            $table->string('from', 500)->nullable()->change();
            $table->string('to', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_ticket_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('from')->nullable()->change();
            $table->string('to')->nullable()->change();
        });
    }
};
