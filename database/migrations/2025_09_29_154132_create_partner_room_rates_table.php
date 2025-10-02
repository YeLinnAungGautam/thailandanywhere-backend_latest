<?php

use App\Models\Partner;
use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('partner_room_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Partner::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Room::class)->constrained()->onDelete('cascade');

            $table->date('date');
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('discount', 10, 2)->default(0)->nullable();

            $table->timestamps();

            $table->unique(['partner_id', 'room_id', 'date']);
            $table->index(['partner_id', 'room_id', 'date'], 'idx_partner_room_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_room_rates');
    }
};
