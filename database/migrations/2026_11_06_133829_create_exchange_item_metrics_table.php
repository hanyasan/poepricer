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
        Schema::create('exchange_item_metrics', function (Blueprint $table) {
            $table->id();

            $table->dateTimeTz('bucket_start');
            $table->double('price');
            $table->double('log_return')->nullable();
            $table->double('residual')->nullable();
            $table->double('residual_mad')->nullable();
            $table->unsignedSmallInteger('observations')->default(0);

            $table->foreignIdFor(ExchangeItem::class, 'exchange_item_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ExchangeMetricsItem::class, 'exchange_metrics_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique(['exchange_item_id', 'exchange_metrics_item_id', 'bucket_start'], 'exchange_item_metrics_unique');
            $table->index(['exchange_metrics_item_id', 'bucket_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_item_metrics');
    }
};
