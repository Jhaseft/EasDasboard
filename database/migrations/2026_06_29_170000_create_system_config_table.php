<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración global editable por el administrador. Es una tabla de fila
     * única (sin id): cada columna es un ajuste. Se irán agregando columnas según
     * se necesiten. Evita tocar variables de entorno para ajustes operativos.
     */
    public function up(): void
    {
        Schema::create('system_config', function (Blueprint $table) {
            $table->decimal('usd_to_bob', 8, 4)->default(6.9);
        });

        // Fila única inicial, sembrada con el valor actual del .env.
        DB::table('system_config')->insert([
            'usd_to_bob' => (float) config('services.baneco.usd_to_bob', 6.9),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_config');
    }
};
