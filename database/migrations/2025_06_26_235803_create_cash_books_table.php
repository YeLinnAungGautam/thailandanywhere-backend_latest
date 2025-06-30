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
        Schema::create('cash_books', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 100)->unique();
            $table->dateTime('date');
            $table->string('income_or_expense')->nullable()->default('expense');
            $table->foreignId('cash_structure_id')->constrained('cash_structures')->onDelete('cascade');
            $table->string('interact_bank', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['date', 'income_or_expense']);
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_books');
    }
};
