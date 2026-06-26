<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotTrade;
use App\Models\BrokerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints que consume el worker de Python (MetaApi). Protegido con la misma
 * API key del runner (middleware api.key).
 *
 * Devuelve las cuentas de broker operables y, dentro de cada una, los bots
 * activos con su configuracion para que el worker abra/gestione operaciones.
 */
class WorkerController extends Controller
{
    public function accounts(): JsonResponse
    {
        $accounts = BrokerAccount::query()
            ->where('is_enabled', true)
            ->where('provision_state', 'deployed')
            ->whereNotNull('metaapi_account_id')
            ->with(['bots' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn (BrokerAccount $account) => [
                'broker_account_id' => $account->id,
                'metaapi_account_id' => $account->metaapi_account_id,
                'region' => $account->region,
                'platform' => $account->platform,
                'bots' => $account->bots->map(function ($bot) {
                    $config = $bot->toOperationConfig();
                    $config['strategy'] = $bot->strategy ?? 'simple';
                    $config['parameters'] = $bot->mergedParameters();

                    return $config;
                })->values(),
            ])
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'metaapi_token' => config('services.metaapi.token'),
            'count' => $accounts->count(),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Recibe el reporte del worker cuando intenta abrir una operacion.
     * El worker manda: bot_id, status (opened|rejected_limits|failed) y, segun
     * el caso, symbol/direction/position_id/SL/TP/error. Lo guardamos para que
     * el usuario vea en el panel si la operacion entro o por que no.
     */
    public function trades(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_id'            => ['required', 'integer', 'exists:bots,id'],
            'broker_account_id' => ['nullable', 'integer', 'exists:broker_accounts,id'],
            'symbol'            => ['required', 'string', 'max:50'],
            'direction'         => ['nullable', 'in:buy,sell'],
            'status'            => ['required', 'in:opened,rejected_limits,failed'],
            'position_id'       => ['nullable', 'string', 'max:100'],
            'requested_sl'      => ['nullable', 'numeric'],
            'requested_tp'      => ['nullable', 'numeric'],
            'error'             => ['nullable', 'string'],
        ]);

        $trade = BotTrade::create($data);

        return response()->json(['ok' => true, 'id' => $trade->id], 201);
    }
}
