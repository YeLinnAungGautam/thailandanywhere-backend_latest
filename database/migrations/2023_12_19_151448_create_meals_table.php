<?php

use App\Models\Restaurant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Restaurant::class, 'restaurant_id');
            $table->string('name');
            $table->bigInteger('extra_price')->nullable();
            $table->bigInteger('meal_price')->nullable();
            $table->text("description")->nullable();
            $table->string('cost')->nullable();
            $table->string('max_person')->nullable();
            $table->boolean('is_extra')->default('0');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
