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
        Schema::create('exchange_types', function (Blueprint $table) {
            $table->id();

            $table->string('category');
            $table->string('value');

            $table->foreignIdFor(PoeVersion::class, 'poe_version_id')->constrained();

            $table->unique(['poe_version_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_types');
    }
};
