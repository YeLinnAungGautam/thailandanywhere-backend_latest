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
        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->after('price', function ($table) {
                $table->decimal('child_cost_price', 10, 2)->nullable();
                $table->decimal('child_owner_price', 10, 2)->nullable();
                $table->decimal('child_agent_price', 10, 2)->nullable();
                $table->decimal('child_price', 10, 2)->nullable();
                $table->text('adult_info')->nullable();
                $table->text('child_info')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrance_ticket_variations', function (Blueprint $table) {
            $table->dropColumn([
                'child_cost_price',
                'child_owner_price',
                'child_agent_price',
                'child_price',
                'adult_info',
                'child_info'
            ]);
        });
    }
};
