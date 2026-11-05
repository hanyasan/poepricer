<?php

use App\Models\Poe\Exchange\Compact\CompactPriceCheckCore;
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
        Schema::create('compact_price_check_core_types', function (Blueprint $table) {
            $table->id();

            $table->float('rate');
            $table->string('exchange_item_trade_id', 255);
            $table->string('name', 255);

            $table->foreignIdFor(CompactPriceCheckCore::class, 'compact_price_check_core_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compact_price_check_core_types');
    }
};
