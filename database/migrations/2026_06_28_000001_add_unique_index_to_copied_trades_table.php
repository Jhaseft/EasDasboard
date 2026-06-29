<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Evita que el worker copie dos veces la misma posición de la maestra en la
     * misma esclava (la copia automática es idempotente por este par).
     */
    public function up(): void
    {
        Schema::table('copied_trades', function (Blueprint $table) {
            $table->unique(['slave_account_id', 'master_position_id'], 'copied_trades_slave_master_pos_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copied_trades', function (Blueprint $table) {
            $table->dropUnique('copied_trades_slave_master_pos_unique');
        });
    }
};
