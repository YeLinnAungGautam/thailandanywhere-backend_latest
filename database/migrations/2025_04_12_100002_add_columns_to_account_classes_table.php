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
        Schema::table('account_classes', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->foreignId('account_head_id')->nullable()->constrained('account_heads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_classes', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropForeign(['account_head_id']);
            $table->dropColumn('account_head_id');
        });
    }
};
