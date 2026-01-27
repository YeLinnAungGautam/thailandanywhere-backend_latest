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
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('user_sessions')->cascadeOnDelete();

            // Product info
            $table->string('product_type', 20)->nullable(); // hotel, attraction, vantour, destination, inclusive
            $table->unsignedBigInteger('product_id')->nullable();

            // Event info
            $table->string('event_type', 30); // visit_site, view_detail, add_to_cart, go_checkout, complete_purchase
            $table->decimal('event_value', 10, 2)->nullable(); // Price, revenue
            $table->integer('quantity')->default(1);

            // Extra data (flexible)
            $table->json('metadata')->nullable(); // {"check_in": "2025-02-01", "check_out": "2025-02-05"}

            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast queries
            $table->index('session_id');
            $table->index('product_type');
            $table->index('product_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['product_type', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};
