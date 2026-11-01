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
        Schema::create('poe_versions', function (Blueprint $table) {
            $table->id();

            $table->string('version');
        });

        DB::table('poe_versions')->insert([
            [
                'id' => 1,
                'version' => 'poe1',
            ],
            [
                'id' => 2,
                'version' => 'poe2',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poe_versions');
    }
};
