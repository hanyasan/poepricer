<?php

use App\Models\Poe\Exchange\ExchangeType;
use App\Models\Poe\Exchange\PriceCheck;
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
        Schema::create('price_check_cores', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(ExchangeType::class, 'exchange_type_id');
            $table->foreignIdFor(PriceCheck::class, 'price_check_id')->constrained()->cascadeOnDelete();

            $table->unique(['exchange_type_id', 'price_check_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_check_cores');
    }
};
