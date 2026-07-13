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
        Schema::create('promos', function (Blueprint $table) {
            $table->id('promo_id');
            $table->string('promo_name');
            $table->text('promo_des')->nullable();
            $table->string('promo_code')->unique();

            $table->enum('promo_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('promo_amount', 10, 2);

            $table->unsignedInteger('promo_count')->default(1);
            $table->unsignedInteger('promo_used_count')->default(0);

            $table->boolean('promo_active')->default(true);
            $table->dateTime('promo_start_date')->nullable();
            $table->dateTime('promo_end_date');

            // 'all' = every product type/item eligible
            // 'specific' = only what's listed in applicable_products
            $table->enum('promo_applies_to', ['all', 'specific'])->default('all');

            // e.g. {"hotel":"all","entrance_ticket":[10,11],"vantour":[]}
            $table->json('applicable_products')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
