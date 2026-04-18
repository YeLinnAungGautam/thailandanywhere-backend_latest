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
        Schema::table('order_items', function (Blueprint $table) {
            // ကလေး စျေးနှုန်းများ သီးသန့် column များ
            $table->decimal('child_price', 10, 2)->nullable();
            $table->decimal('child_cost', 10, 2)->nullable();
            $table->decimal('child_total_selling_price', 10, 2)->nullable();
            $table->decimal('child_total_cost', 10, 2)->nullable();
            $table->integer('child_quantity')->nullable();

            // အရွယ်ရောက်ပြီးသူ (လိုအပ်ရင်)
            $table->decimal('adult_price', 10, 2)->nullable()->after('child_quantity');
            $table->decimal('adult_cost', 10, 2)->nullable()->after('adult_price');
            $table->decimal('adult_total_selling_price', 10, 2)->nullable()->after('adult_price');
            $table->decimal('adult_total_cost', 10, 2)->nullable()->after('adult_cost');
            $table->integer('adult_quantity')->nullable()->after('adult_cost');

            // နောက်ထပ် pricing types (infant, senior, etc.)
            $table->decimal('infant_price', 10, 2)->nullable()->after('adult_quantity');
            $table->decimal('infant_cost', 10, 2)->nullable()->after('infant_price');
            $table->decimal('infant_total_selling_price', 10, 2)->nullable()->after('infant_price');
            $table->decimal('infant_total_cost', 10, 2)->nullable()->after('infant_cost');
            $table->integer('infant_quantity')->nullable()->after('infant_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'child_price', 'child_cost', 'child_quantity',
                'adult_price', 'adult_cost', 'adult_quantity',
                'infant_price', 'infant_cost', 'infant_quantity',
                'child_total_selling_price', 'child_total_cost',
                'adult_total_selling_price', 'adult_total_cost',
                'infant_total_selling_price', 'infant_total_cost'

            ]);
        });
    }
};
