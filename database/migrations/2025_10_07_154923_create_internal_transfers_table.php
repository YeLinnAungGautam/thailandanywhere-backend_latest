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
        Schema::create('internal_transfers', function (Blueprint $table) {
            $table->id();
            $table->decimal('exchange_rate', 15, 6);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('internal_transfer_cash_images', function (Blueprint $table) {
            $table->id();

            // Use unsignedBigInteger instead of foreignId to avoid auto-index
            $table->unsignedBigInteger('internal_transfer_id');
            $table->unsignedBigInteger('cash_image_id');
            $table->enum('direction', ['from', 'to']);
            $table->timestamps();

            // Manually add foreign key constraints with custom names
            $table->foreign('internal_transfer_id', 'fk_transfer_id')
                  ->references('id')
                  ->on('internal_transfers')
                  ->onDelete('cascade');

            $table->foreign('cash_image_id', 'fk_cash_image_id')
                  ->references('id')
                  ->on('cash_images')
                  ->onDelete('cascade');

            // Add indexes and unique constraint with custom names
            $table->unique(
                ['internal_transfer_id', 'cash_image_id', 'direction'],
                'idx_transfer_img_dir'
            );

            $table->index(
                ['internal_transfer_id', 'direction'],
                'idx_transfer_dir'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_transfer_cash_images');
        Schema::dropIfExists('internal_transfers');
    }
};
