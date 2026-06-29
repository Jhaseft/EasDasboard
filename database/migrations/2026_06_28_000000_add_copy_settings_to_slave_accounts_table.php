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
        Schema::table('slave_accounts', function (Blueprint $table) {
            // Si está activo, el worker copia automáticamente (open + close) las
            // operaciones de la maestra a esta esclava, sin intervención manual.
            $table->boolean('auto_copy')->default(true)->after('lot_multiplier');

            // Cómo se calcula el lote de la esclava:
            //   'multiplier' -> master_lot * lot_multiplier
            //   'fixed'      -> siempre fixed_lot
            $table->string('copy_mode')->default('multiplier')->after('auto_copy');

            // Lote fijo usado cuando copy_mode = 'fixed'.
            $table->decimal('fixed_lot', 8, 2)->nullable()->after('copy_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slave_accounts', function (Blueprint $table) {
            $table->dropColumn(['auto_copy', 'copy_mode', 'fixed_lot']);
        });
    }
};
