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
        // Attempt to add index to booking_items
        try {
            Schema::table('booking_items', function (Blueprint $table) {
                $table->index('service_date');
            });
        } catch (\Exception $e) {
            // Check for duplicate key name error or similar
            if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }

        // Attempt to add index to booking_item_groups
        try {
            Schema::table('booking_item_groups', function (Blueprint $table) {
                $table->index('product_type');
            });
        } catch (\Exception $e) {
            // Check for duplicate key name error or similar
            if (!str_contains($e->getMessage(), 'Duplicate key name') && !str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('booking_items', function (Blueprint $table) {
                $table->dropIndex(['service_date']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('booking_item_groups', function (Blueprint $table) {
                $table->dropIndex(['product_type']);
            });
        } catch (\Exception $e) {}
    }
};
