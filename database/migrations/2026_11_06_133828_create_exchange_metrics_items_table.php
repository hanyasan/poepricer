<?php

use App\Models\Poe\Exchange\ExchangeItem;
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
        Schema::create('exchange_metrics_items', function (Blueprint $table) {
            $table->id();

            $table->float('min_price')->default(0);

            $table->foreignIdFor(PoeLeague::class, 'poe_league_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ExchangeItem::class, 'exchange_item_id')->constrained()->cascadeOnDelete();

            $table->unique(['poe_league_id', 'exchange_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_metrics_items');
    }
};
