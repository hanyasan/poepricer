<?php

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
        Schema::table('price_check_cores', function (Blueprint $table) {
            $table->index('price_check_id');
        });

        Schema::table('price_check_core_types', function (Blueprint $table) {
            $table->index('price_check_core_id');
        });

        Schema::table('exchange_item_prices', function (Blueprint $table) {
            $table->index('price_check_core_id');
        });

        Schema::table('compact_price_check_cores', function (Blueprint $table) {
            $table->index('compact_price_check_id');
        });

        Schema::table('compact_price_check_core_types', function (Blueprint $table) {
            $table->index('compact_price_check_core_id');
        });

        Schema::table('compact_exchange_item_prices', function (Blueprint $table) {
            $table->index('compact_price_check_core_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_check_cores', function (Blueprint $table) {
            $table->dropIndex(['price_check_id']);
        });

        Schema::table('price_check_core_types', function (Blueprint $table) {
            $table->dropIndex(['price_check_core_id']);
        });

        Schema::table('exchange_item_prices', function (Blueprint $table) {
            $table->dropIndex(['price_check_core_id']);
        });

        Schema::table('compact_price_check_cores', function (Blueprint $table) {
            $table->dropIndex(['compact_price_check_id']);
        });

        Schema::table('compact_price_check_core_types', function (Blueprint $table) {
            $table->dropIndex(['compact_price_check_core_id']);
        });

        Schema::table('compact_exchange_item_prices', function (Blueprint $table) {
            $table->dropIndex(['compact_price_check_core_id']);
        });
    }
};
