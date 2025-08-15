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
        Schema::create('cash_imageables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_image_id')->constrained()->cascadeOnDelete();
            $table->morphs('imageable');
            $table->string('type')->nullable();
            $table->decimal('deposit', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_imageables');
    }
};
