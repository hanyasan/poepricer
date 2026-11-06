<?php

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\Metrics\ExchangeMetricsItem;
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
        Schema::create('exchange_item_betas', function (Blueprint $table) {
            $table->id();

            $table->dateTimeTz('calculated_at');
            $table->double('beta');
            $table->double('raw_beta');
            $table->double('r_squared')->nullable();
            $table->unsignedSmallInteger('observations');

            $table->foreignIdFor(ExchangeItem::class, 'exchange_item_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ExchangeMetricsItem::class, 'exchange_metrics_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique(['exchange_item_id', 'exchange_metrics_item_id', 'calculated_at'], 'exchange_item_betas_unique');
            $table->index(['exchange_metrics_item_id', 'calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_item_betas');
    }
};
