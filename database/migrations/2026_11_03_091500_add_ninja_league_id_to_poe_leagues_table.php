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
        Schema::table('poe_leagues', function (Blueprint $table) {
            $table->string('ninja_league_id')->nullable()->after('league_name');

            $table->dropUnique(['league_name', 'poe_version_id']);
            $table->unique(['ninja_league_id', 'poe_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poe_leagues', function (Blueprint $table) {
            $table->dropUnique(['ninja_league_id', 'poe_version_id']);
            $table->unique(['league_name', 'poe_version_id']);

            $table->dropColumn('ninja_league_id');
        });
    }
};
