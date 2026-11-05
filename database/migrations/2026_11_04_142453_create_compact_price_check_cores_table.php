<?php

use App\Models\Poe\Exchange\Compact\CompactPriceCheck;
use App\Models\Poe\Exchange\ExchangeType;
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
        Schema::create('compact_price_check_cores', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(ExchangeType::class, 'exchange_type_id')->constrained();
            $table->foreignIdFor(CompactPriceCheck::class, 'compact_price_check_id')->constrained()->cascadeOnDelete();

            $table->unique(['exchange_type_id', 'compact_price_check_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compact_price_check_cores');
    }
};
