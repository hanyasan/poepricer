<?php

use App\Models\Poe\PoeLeague;
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
        Schema::create('exchange_items', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('trade_id');

            $table->foreignIdFor(PoeLeague::class, 'poe_league_id')->constrained();

            $table->unique(['trade_id', 'poe_league_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
    }
};
