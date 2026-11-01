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
        Schema::create('league_check_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('minutes_check')->nullable();

            $table->foreignIdFor(PoeLeague::class, 'poe_league_id')->unique()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_check_rules');
    }
};
