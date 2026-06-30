<?php

namespace App\Console\Commands;

use App\Services\Binance\BinanceApiService;
use App\Services\Binance\BinanceDepositService;
use Illuminate\Console\Command;

class PollBinanceDeposits extends Command
{
    protected $signature = 'binance:poll';

    protected $description = 'Detecta depósitos USDT en Binance y acredita los intents pendientes (respaldo del polling del frontend)';

    public function handle(BinanceApiService $api, BinanceDepositService $binance): int
    {
        if (! $api->isConfigured()) {
            $this->warn('Binance no está configurado; nada que hacer.');

            return self::SUCCESS;
        }

        // Una sola consulta al historial acredita todos los pendientes que cuadren.
        $binance->matchPendingIntent();

        $expired = $binance->expireOverdue();

        $this->info("Binance poll OK. Intents expirados: {$expired}");

        return self::SUCCESS;
    }
}
