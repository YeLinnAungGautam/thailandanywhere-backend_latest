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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('check_in')->nullable();
            $table->string('check_out')->nullable();
            $table->string('cancellation_policy')->nullable();
            $table->string('official_address')->nullable();
            $table->string('official_logo')->nullable();
            $table->string('official_phone_number')->nullable();
            $table->string('official_email')->nullable();
            $table->string('official_remark')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'check_in',
                'check_out',
                'cancellation_policy',
                'official_address',
                'official_logo',
                'official_phone_number',
                'official_email',
                'official_remark'
            ]);
        });
    }
};
