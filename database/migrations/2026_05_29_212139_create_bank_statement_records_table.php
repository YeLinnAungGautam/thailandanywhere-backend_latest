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
        Schema::create('bank_statement_records', function (Blueprint $table) {
            $table->id();

            // Which upload batch this row belongs to (month + year + account)
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('account_number', 50)->nullable(); // e.g. 198-1-06668-1

            // Raw CSV fields
            $table->date('txn_date');
            $table->time('txn_time')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('withdrawal', 15, 2)->nullable();   // ถอนเงิน
            $table->decimal('deposit', 15, 2)->nullable();       // ฝากเงิน
            $table->decimal('balance', 15, 2)->nullable();       // ยอดคงเหลือ
            $table->string('channel', 100)->nullable();          // ช่องทาง
            $table->string('detail', 500)->nullable();           // รายละเอียด

            // Verification link
            $table->unsignedBigInteger('cash_image_id')->nullable()->index();
            $table->string('duplicate_ids',500)->nullable();
            $table->string('verified')->nullable();

            $table->timestamps();

            // Unique constraint so re-uploads within same month/year don't duplicate
            // (date + time + amount combo, scoped to month/year)
            $table->index(['month', 'year']);
            $table->index(['txn_date', 'txn_time']);

            $table->foreign('cash_image_id')
                  ->references('id')
                  ->on('cash_images')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statement_records');
    }
};
