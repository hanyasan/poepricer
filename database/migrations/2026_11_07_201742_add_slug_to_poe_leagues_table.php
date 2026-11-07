<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('poe_leagues', function (Blueprint $table) {
            $table->string('slug')->nullable();
        });

        DB::table('poe_leagues')
            ->join('poe_versions', 'poe_versions.id', '=', 'poe_leagues.poe_version_id')
            ->select('poe_leagues.id', 'poe_leagues.ninja_league_id', 'poe_versions.version')
            ->get()
            ->each(static function (object $league): void {
                DB::table('poe_leagues')
                    ->where('id', $league->id)
                    ->update(['slug' => $league->version.'-'.Str::slug($league->ninja_league_id)]);
            });

        Schema::table('poe_leagues', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poe_leagues', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
