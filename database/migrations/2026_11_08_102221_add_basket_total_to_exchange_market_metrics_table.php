<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exchange_market_metrics', function (Blueprint $table) {
            $table->unsignedInteger('basket_total')->default(0);
        });

        DB::statement('
            UPDATE exchange_market_metrics
            SET basket_total = (
                SELECT COUNT(*)
                FROM exchange_item_metrics
                WHERE exchange_item_metrics.exchange_metrics_item_id = exchange_market_metrics.exchange_metrics_item_id
                    AND exchange_item_metrics.bucket_start = exchange_market_metrics.bucket_start
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_market_metrics', function (Blueprint $table) {
            $table->dropColumn('basket_total');
        });
    }
};
