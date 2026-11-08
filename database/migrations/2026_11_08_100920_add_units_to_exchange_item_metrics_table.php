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
        Schema::table('exchange_item_metrics', function (Blueprint $table) {
            $table->double('units')->nullable();
            $table->double('return_weight')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_item_metrics', function (Blueprint $table) {
            $table->dropColumn(['units', 'return_weight']);
        });
    }
};
