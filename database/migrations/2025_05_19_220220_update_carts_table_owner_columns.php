<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cart
        Schema::table('carts', function (Blueprint $table) {
            // Add new columns
            $table->unsignedBigInteger('owner_id')->nullable()->after('id');
            $table->string('owner_type')->nullable()->after('owner_id');
        });

        // Copy existing user_id data to new columns (outside the schema closure)
        DB::statement("UPDATE carts SET owner_id = user_id, owner_type = 'App\\Models\\User'");

        Schema::table('carts', function (Blueprint $table) {
            // Make owner columns non-nullable after data is copied
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            $table->string('owner_type')->nullable(false)->change();

            // Remove old user_id column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');

            // Add index
            $table->index(['owner_id', 'owner_type']);
        });

        // order
        Schema::table('orders', function (Blueprint $table) {
            // Modify the enum column by recreating it
            $table->enum('order_status', ['pending', 'processing', 'confirmed', 'cancelled', 'sale_convert'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        // cart drop
        Schema::table('carts', function (Blueprint $table) {
            // Add back user_id
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        // Copy data back for User owners (outside the schema closure)
        DB::statement("UPDATE carts SET user_id = owner_id WHERE owner_type = 'App\\Models\\User'");

        Schema::table('carts', function (Blueprint $table) {
            // Add foreign key
            $table->foreign('user_id')->references('id')->on('users');

            // Remove polymorphic columns
            $table->dropIndex(['owner_id', 'owner_type']);
            $table->dropColumn(['owner_id', 'owner_type']);
        });

        // order drop
        Schema::table('orders', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('order_status', ['pending', 'processing', 'confirmed', 'cancelled'])
                  ->default('pending')
                  ->change();
        });
    }
};
