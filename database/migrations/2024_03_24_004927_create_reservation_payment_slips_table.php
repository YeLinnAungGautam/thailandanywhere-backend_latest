<?php

use App\Models\ReservationTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_payment_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ReservationTransaction::class);
            $table->string('file');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_payment_slips');
    }
};
