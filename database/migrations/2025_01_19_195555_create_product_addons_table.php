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
        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->morphs('productable');
            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2)->nullable()->comment('This is the price that will be added to the product price');
            $table->decimal('cost_price', 10, 2)->nullable()->comment('This is the cost price to pay for the addon provider like hotel, ticket seller etc');

            $table->boolean('is_active')->default(true);
            $table->integer('limit')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_addons');
    }
};
