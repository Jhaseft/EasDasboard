<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotTrade;
use App\Models\BrokerAccount;
use App\Models\CopiedTrade;
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
     * Configuración de copy-trading para el worker.
     *
     * Devuelve cada cuenta maestra operable que tenga al menos una esclava con
     * copia automática, junto con sus esclavas y las operaciones ya copiadas que
     * siguen abiertas (open_trades). El worker compara las posiciones reales de
     * la maestra contra open_trades para decidir qué abrir y qué cerrar.
     */
    public function copyAccounts(): JsonResponse
    {
        $masters = BrokerAccount::query()
            ->where('is_enabled', true)
            ->where('provision_state', 'deployed')
            ->whereNotNull('metaapi_account_id')
            ->whereHas('slaveAccounts', fn ($q) => $q
                ->where('auto_copy', true)
                ->where('is_enabled', true)
                ->where('provision_state', 'deployed')
                ->whereNotNull('metaapi_account_id'))
            ->with(['slaveAccounts' => fn ($q) => $q
                ->where('auto_copy', true)
                ->where('is_enabled', true)
                ->where('provision_state', 'deployed')
                ->whereNotNull('metaapi_account_id')])
            ->get()
            ->map(function (BrokerAccount $master) {
                $openTrades = CopiedTrade::where('master_account_id', $master->id)
                    ->where('status', 'open')
                    ->get(['id', 'slave_account_id', 'master_position_id', 'slave_position_id', 'symbol']);

                return [
                    'master_account_id' => $master->id,
                    'metaapi_account_id' => $master->metaapi_account_id,
                    'region' => $master->region,
                    'slaves' => $master->slaveAccounts->map(fn ($slave) => [
                        'slave_account_id' => $slave->id,
                        'metaapi_account_id' => $slave->metaapi_account_id,
                        'region' => $slave->region,
                        'copy_mode' => $slave->copy_mode ?? 'multiplier',
                        'lot_multiplier' => (float) $slave->lot_multiplier,
                        'fixed_lot' => $slave->fixed_lot !== null ? (float) $slave->fixed_lot : null,
                    ])->values(),
                    'open_trades' => $openTrades->map(fn ($t) => [
                        'copied_trade_id' => $t->id,
                        'slave_account_id' => $t->slave_account_id,
                        'master_position_id' => $t->master_position_id,
                        'slave_position_id' => $t->slave_position_id,
                        'symbol' => $t->symbol,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'metaapi_token' => config('services.metaapi.token'),
            'count' => $masters->count(),
            'masters' => $masters,
        ]);
    }

    /**
     * El worker reporta las acciones de copy-trading que ejecutó para que el
     * panel actualice copied_trades. Es idempotente: las aperturas se identifican
     * por (slave_account_id, master_position_id) y los cierres por copied_trade_id.
     */
    public function reportCopyTrades(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opened' => ['array'],
            'opened.*.master_account_id' => ['required', 'integer'],
            'opened.*.slave_account_id' => ['required', 'integer'],
            'opened.*.master_position_id' => ['required', 'string'],
            'opened.*.slave_position_id' => ['nullable', 'string'],
            'opened.*.symbol' => ['required', 'string'],
            'opened.*.direction' => ['required', 'in:buy,sell'],
            'opened.*.master_lot' => ['required', 'numeric'],
            'opened.*.slave_lot' => ['required', 'numeric'],
            'opened.*.status' => ['required', 'in:open,failed'],
            'opened.*.error' => ['nullable', 'string'],

            'closed' => ['array'],
            // El cierre se identifica por copied_trade_id O por el par
            // (slave_account_id, master_position_id) — lo que el worker tenga a mano.
            'closed.*.copied_trade_id' => ['nullable', 'integer'],
            'closed.*.slave_account_id' => ['nullable', 'integer'],
            'closed.*.master_position_id' => ['nullable', 'string'],
            'closed.*.status' => ['required', 'in:closed,failed'],
            'closed.*.error' => ['nullable', 'string'],
        ]);

        foreach ($data['opened'] ?? [] as $event) {
            CopiedTrade::updateOrCreate(
                [
                    'slave_account_id' => $event['slave_account_id'],
                    'master_position_id' => $event['master_position_id'],
                ],
                [
                    'master_account_id' => $event['master_account_id'],
                    'slave_position_id' => $event['slave_position_id'] ?? null,
                    'symbol' => $event['symbol'],
                    'direction' => $event['direction'],
                    'master_lot' => $event['master_lot'],
                    'slave_lot' => $event['slave_lot'],
                    'status' => $event['status'],
                    'error' => $event['error'] ?? null,
                    'opened_at' => now(),
                ]
            );
        }

        foreach ($data['closed'] ?? [] as $event) {
            $query = CopiedTrade::query();

            if (! empty($event['copied_trade_id'])) {
                $query->where('id', $event['copied_trade_id']);
            } elseif (! empty($event['slave_account_id']) && ! empty($event['master_position_id'])) {
                $query->where('slave_account_id', $event['slave_account_id'])
                    ->where('master_position_id', $event['master_position_id']);
            } else {
                continue;
            }

            $query->update([
                'status' => $event['status'],
                'error' => $event['error'] ?? null,
                'closed_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'opened' => count($data['opened'] ?? []),
            'closed' => count($data['closed'] ?? []),
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
