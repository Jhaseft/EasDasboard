<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            // Tipo de estrategia: 'simple' (direccion fija) o 'asian_breakout'.
            $table->string('strategy', 40)->default('simple')->after('timeframe');
            // Parametros editables propios de la estrategia (JSON).
            $table->json('parameters')->nullable()->after('strategy');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['strategy', 'parameters']);
        });
    }
};
