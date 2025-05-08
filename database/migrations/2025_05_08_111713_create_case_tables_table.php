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
        Schema::create('case_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('related_id');
            $table->enum('case_type', ['sale', 'cost']);
            $table->enum('verification_status', ['verified', 'issue'])->default('issue');
            $table->string('name')->nullable();
            $table->text('detail')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['case_type', 'related_id']);
            $table->index('verification_status');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_tables');
    }
};
