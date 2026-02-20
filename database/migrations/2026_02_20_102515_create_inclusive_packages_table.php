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
        Schema::create('inclusive_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('adults')->default(2);
            $table->unsignedInteger('children')->default(0);
            $table->date('start_date');
            $table->unsignedInteger('nights');
            $table->date('end_date');
            $table->unsignedInteger('total_days');

            // အားလုံးကို JSON format နဲ့ save
            $table->json('day_city_map');      // dayCityMap
            $table->json('attractions');       // attractions array
            $table->json('hotels');            // hotels array
            $table->json('van_tours');         // vanTours array
            $table->json('ordered_items');     // orderedItems array
            $table->json('descriptions');      // descriptions object

            // Summary pricing (filter လုပ်ဖို့ပဲ column ခွဲ)
            $table->decimal('total_cost_price', 12, 2)->default(0);
            $table->decimal('total_selling_price', 12, 2)->default(0);

            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inclusive_packages');
    }
};
