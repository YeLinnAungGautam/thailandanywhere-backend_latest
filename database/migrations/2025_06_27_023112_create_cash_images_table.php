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
        Schema::create('cash_images', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->dateTime('date')->nullable();
            $table->string('sender', 255)->nullable();
            $table->string('receiver', 255)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('interact_bank', 255)->nullable()->default('personal');
            $table->string('currency', 10)->default('THB');

            // Polymorphic columns
            $table->morphs('relatable'); // Creates relatable_type and relatable_id

            $table->timestamps();

            // $table->index('date'); // no need to index
            // $table->index('amount'); //  no need to index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_images');
    }
};
