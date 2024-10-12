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
        Schema::create('entrance_ticket_variation_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entrance_ticket_variation_id');
            $table->string('period_name');
            $table->string('period_type');
            $table->string('period')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->decimal('cost_price')->nullable();
            $table->decimal('owner_price')->nullable();
            $table->decimal('agent_price')->nullable();
            $table->decimal('price')->nullable();

            $table->decimal('child_cost_price', 10, 2)->nullable();
            $table->decimal('child_owner_price', 10, 2)->nullable();
            $table->decimal('child_agent_price', 10, 2)->nullable();
            $table->decimal('child_price', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrance_ticket_variation_periods');
    }
};
