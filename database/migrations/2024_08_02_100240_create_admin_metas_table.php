<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Admin::class)->constrained()->cascadeOnDelete();
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_metas');
    }
};
