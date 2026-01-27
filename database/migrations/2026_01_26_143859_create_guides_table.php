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
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('licence')->nullable();
            $table->string('contact')->nullable();
            $table->string('image')->nullable();
            $table->integer('day_rate')->nullable();
            $table->text('notes')->nullable();
            $table->integer('renew_score')->default(0);
            $table->boolean('is_active')->default(1);
            $table->json('languages')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
