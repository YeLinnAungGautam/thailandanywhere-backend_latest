<?php

use App\Models\City;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->after('entry_fee', function ($table) {
                $table->foreignIdFor(City::class, 'city_id')->nullable();
                $table->string('feature_img')->nullable();
                $table->longText('summary')->nullable();
                $table->longText('detail')->nullable();
                $table->text('place_id')->nullable()->comment('Google Map URL');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('city_id');
            $table->dropColumn('feature_img');
            $table->dropColumn('summary');
            $table->dropColumn('detail');
            $table->dropColumn('place_id');
        });
    }
};
