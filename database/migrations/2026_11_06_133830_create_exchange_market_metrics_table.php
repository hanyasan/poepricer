<?php

use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
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
        Schema::create('exchange_market_metrics', function (Blueprint $table) {
            $table->id();

            $table->dateTimeTz('bucket_start');
            $table->double('market_return');
            $table->unsignedInteger('basket_size');

            $table->foreignIdFor(PoeLeague::class, 'poe_league_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ExchangeMetricsItem::class, 'exchange_metrics_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique(['exchange_metrics_item_id', 'bucket_start']);
            $table->index('poe_league_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_market_metrics');
    }
};
