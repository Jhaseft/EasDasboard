<?php

namespace App\Jobs;

use App\Models\Signal;
use App\Services\MetaApi\MetaApiTrading;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ejecuta una señal recibida (webhook) en la cuenta dueña vía MetaApi REST.
 *
 * IMPORTANTE: este job NO reparte a las cuentas esclavas. Solo ejecuta en la
 * cuenta de la señal. Si esa cuenta es maestra, el worker de Python (lectura por
 * streaming) detecta la nueva posición y la copia a las esclavas. Así hay un
 * único motor de copia y no se duplican operaciones.
 */
class ExecuteSignal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(public Signal $signal) {}

    public function handle(MetaApiTrading $trading): void
    {
        $signal = $this->signal;
        $account = $signal->brokerAccount;

        if (! $account || ! $account->isOperable()) {
            $signal->forceFill([
                'status' => 'failed',
                'error'  => 'La cuenta no está operable (sin desplegar o deshabilitada).',
            ])->save();

            return;
        }

        try {
            $comment = 'sig:'.$signal->id;

            if ($signal->action === 'close') {
                $trading->closePositionsBySymbol($account, $signal->symbol);
            } else {
                $trading->openPosition(
                    account: $account,
                    symbol: $signal->symbol,
                    direction: $signal->action,
                    volume: (float) $signal->volume,
                    sl: $signal->sl !== null ? (float) $signal->sl : null,
                    tp: $signal->tp !== null ? (float) $signal->tp : null,
                    comment: $comment,
                );
            }

            $signal->forceFill([
                'status'      => 'executed',
                'error'       => null,
                'executed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $signal->forceFill([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->signal->forceFill([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ])->save();
    }
}
