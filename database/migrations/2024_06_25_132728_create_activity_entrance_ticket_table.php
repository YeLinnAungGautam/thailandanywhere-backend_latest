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
        Schema::create('activity_entrance_ticket', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entrance_ticket_id');
            $table->unsignedBigInteger('attraction_activity_id');
            $table->timestamps();

            $table->foreign('entrance_ticket_id')->references('id')->on('entrance_tickets')->onDelete('cascade');
            $table->foreign('attraction_activity_id')->references('id')->on('attraction_activities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_entrance_ticket');
    }
};
