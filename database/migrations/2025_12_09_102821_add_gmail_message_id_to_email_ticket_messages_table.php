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
            $table->unsignedBigInteger('ticket_id')->nullable()->after('id');
            $table->string('from')->nullable()->after('ticket_id');
            $table->string('to')->nullable()->after('from');
            $table->text('body')->nullable()->after('to');
            $table->string('gmail_message_id')->nullable()->unique()->after('body');
            $table->datetime('gmail_datetime')->nullable()->after('gmail_message_id');
            $table->boolean('is_incoming')->default(false)->after('gmail_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_ticket_messages', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_id',
                'from',
                'to',
                'body',
                'gmail_message_id',
                'gmail_datetime',
                'is_incoming',
            ]);
        });
    }
};
