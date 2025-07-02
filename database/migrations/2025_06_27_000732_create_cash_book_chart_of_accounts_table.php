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
        Schema::create('cash_book_chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_book_id')->constrained('cash_books')->onDelete('cascade');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->onDelete('cascade');
            $table->decimal('allocated_amount', 15, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            // Unique constraint
            $table->index('allocated_amount'); // remove index
            // $table->index(['cash_book_id', 'chart_of_account_id']); // need to index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_book_chart_of_accounts');
    }
};
