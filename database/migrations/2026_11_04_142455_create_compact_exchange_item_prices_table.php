<?php

use App\Models\Poe\Exchange\Compact\CompactPriceCheckCore;
use App\Models\Poe\Exchange\ExchangeItem;
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
        Schema::create('compact_exchange_item_prices', function (Blueprint $table) {
            $table->id();

            $table->float('rate');

            $table->foreignIdFor(ExchangeItem::class, 'exchange_item_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(CompactPriceCheckCore::class, 'compact_price_check_core_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compact_exchange_item_prices');
    }
};
