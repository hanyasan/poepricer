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
        Schema::create('price_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(PoeLeague::class, 'poe_league_id')->constrained();

            $table->dateTimeTz('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_checks');
    }
};
