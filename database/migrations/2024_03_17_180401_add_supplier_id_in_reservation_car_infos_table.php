<?php

use App\Models\Driver;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservation_car_infos', function (Blueprint $table) {
            $table->after('booking_item_id', function ($table) {
                $table->foreignIdFor(Supplier::class)->nullable();
                $table->foreignIdFor(Driver::class)->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_car_infos', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'driver_id']);
        });
    }
};
