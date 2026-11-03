<?php

use App\Models\Poe\PoeVersion;
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
        Schema::create('poe_leagues', function (Blueprint $table) {
            $table->id();

            $table->string('league_name');
            $table->string('ninja_league_id')->nullable()->after('league_name');
            $table->boolean('is_active')->default(false);

            $table->foreignIdFor(PoeVersion::class, 'poe_version_id')->constrained();

            $table->unique(['ninja_league_id', 'poe_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poe_leagues');
    }
};
