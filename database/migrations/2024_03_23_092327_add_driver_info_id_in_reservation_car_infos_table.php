<?php

use App\Models\DriverInfo;
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
            $table->foreignIdFor(DriverInfo::class)->nullable()->after('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_car_infos', function (Blueprint $table) {
            $table->dropColumn('driver_info_id');
        });
    }
};
