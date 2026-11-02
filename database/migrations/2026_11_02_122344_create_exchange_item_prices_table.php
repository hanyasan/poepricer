<?php

use App\Models\Poe\Exchange\ExchangeItem;
use App\Models\Poe\Exchange\PriceCheckCore;
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
        Schema::create('exchange_item_prices', function (Blueprint $table) {
            $table->id();

            $table->float('rate');

            $table->foreignIdFor(ExchangeItem::class, 'exchange_item_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PriceCheckCore::class, 'price_check_core_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_item_prices');
    }
};
