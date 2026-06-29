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
        Schema::table('broker_accounts', function (Blueprint $table) {
            // Token único para la ingesta por webhook (TradingView / MT5 / Python).
            // La señal llega a POST /api/webhook/{webhook_token}.
            $table->string('webhook_token', 64)->nullable()->unique()->after('metaapi_account_id');

            // Cómo entran las señales de esta cuenta:
            //   'read'    -> solo lectura por streaming (copia lo que el usuario opera)
            //   'webhook' -> solo señales externas por webhook
            //   'both'    -> ambas
            $table->string('ingest_mode')->default('both')->after('webhook_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broker_accounts', function (Blueprint $table) {
            $table->dropColumn(['webhook_token', 'ingest_mode']);
        });
    }
};
