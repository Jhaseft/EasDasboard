<?php

namespace App\Http\Controllers;

use App\Models\BrokerAccount;
use App\Models\CopiedTrade;
use App\Models\SlaveAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class CopyTradeController extends Controller
{
    /**
     * Lista las operaciones abiertas en la cuenta maestra via MetaApi
     * y las esclavas disponibles para copiar.
     */
    public function index(Request $request, BrokerAccount $brokerAccount): Response
    {
        abort_unless($brokerAccount->user_id === $request->user()->id, 403);

        $positions = $this->fetchOpenPositions($brokerAccount);

        $slaves = $brokerAccount->slaveAccounts()
            ->where('is_enabled', true)
            ->where('provision_state', 'deployed')
            ->get(['id', 'name', 'login', 'lot_multiplier']);

        $history = CopiedTrade::where('master_account_id', $brokerAccount->id)
            ->with('slaveAccount:id,name')
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('CopyTrade/Index', [
            'master'    => $brokerAccount->only('id', 'name', 'login'),
            'positions' => $positions,
            'slaves'    => $slaves,
            'history'   => $history,
        ]);
    }

    /**
     * Copia una operacion de la maestra a las esclavas seleccionadas.
     * Recibe: position_id, direction, symbol, master_lot, slave_account_ids[]
     */
    public function copy(Request $request, BrokerAccount $brokerAccount): RedirectResponse
    {
        abort_unless($brokerAccount->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'master_position_id' => ['required', 'string'],
            'symbol'             => ['required', 'string'],
            'direction'          => ['required', 'in:buy,sell'],
            'master_lot'         => ['required', 'numeric', 'min:0.01'],
            'slave_account_ids'  => ['required', 'array', 'min:1'],
            'slave_account_ids.*'=> ['integer', 'exists:slave_accounts,id'],
        ]);

        $slaves = SlaveAccount::whereIn('id', $data['slave_account_ids'])
            ->where('master_account_id', $brokerAccount->id)
            ->where('is_enabled', true)
            ->where('provision_state', 'deployed')
            ->get();

        foreach ($slaves as $slave) {
            $slaveLot = round((float) $data['master_lot'] * (float) $slave->lot_multiplier, 2);
            $slaveLot = max($slaveLot, 0.01);

            $copiedTrade = CopiedTrade::create([
                'master_account_id'  => $brokerAccount->id,
                'slave_account_id'   => $slave->id,
                'master_position_id' => $data['master_position_id'],
                'symbol'             => $data['symbol'],
                'direction'          => $data['direction'],
                'master_lot'         => $data['master_lot'],
                'slave_lot'          => $slaveLot,
                'status'             => 'pending',
                'opened_at'          => now(),
            ]);

            try {
                $positionId = $this->openPositionOnSlave($slave, $data['symbol'], $data['direction'], $slaveLot);

                $copiedTrade->update([
                    'slave_position_id' => $positionId,
                    'status'            => 'open',
                ]);
            } catch (\Throwable $e) {
                $copiedTrade->update([
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Operación copiada a ' . $slaves->count() . ' cuenta(s) esclava(s).');
    }

    /**
     * Cierra una posicion abierta de la cuenta (maestra) via MetaApi REST,
     * dado el id de la posicion. Lo dispara el boton "Cerrar" del panel.
     */
    public function close(Request $request, BrokerAccount $brokerAccount): RedirectResponse
    {
        abort_unless($brokerAccount->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'position_id' => ['required', 'string'],
        ]);

        if (! $brokerAccount->metaapi_account_id) {
            return back()->with('error', 'La cuenta no esta provisionada en MetaApi.');
        }

        $token   = config('services.metaapi.token');
        $baseUrl = 'https://mt-client-api-v1.' . ($brokerAccount->region ?? 'new-york') . '.agiliumtrade.ai';

        try {
            $response = Http::withHeaders([
                'auth-token'   => $token,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/users/current/accounts/{$brokerAccount->metaapi_account_id}/trade", [
                'actionType' => 'POSITION_CLOSE_ID',
                'positionId' => $data['position_id'],
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al cerrar la operacion: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('error', 'MetaApi rechazo el cierre: ' . $response->body());
        }

        return back()->with('success', 'Operacion cerrada correctamente.');
    }

    /**
     * Abre una posicion en la cuenta esclava via MetaApi REST.
     */
    protected function openPositionOnSlave(SlaveAccount $slave, string $symbol, string $direction, float $lot): string
    {
        $token  = config('services.metaapi.token');
        $baseUrl = 'https://mt-client-api-v1.' . ($slave->region ?? 'new-york') . '.agiliumtrade.ai';

        $response = Http::withHeaders([
            'auth-token'   => $token,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/users/current/accounts/{$slave->metaapi_account_id}/trade", [
            'actionType' => $direction === 'buy' ? 'ORDER_TYPE_BUY' : 'ORDER_TYPE_SELL',
            'symbol'     => $symbol,
            'volume'     => $lot,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('MetaApi trade failed: ' . $response->body());
        }

        return $response->json('positionId') ?? $response->json('orderId') ?? 'unknown';
    }

    /**
     * Obtiene las posiciones abiertas de la maestra via MetaApi REST.
     */
    protected function fetchOpenPositions(BrokerAccount $account): array
    {
        if (! $account->metaapi_account_id) {
            return [];
        }

        $token   = config('services.metaapi.token');
        $baseUrl = 'https://mt-client-api-v1.' . ($account->region ?? 'new-york') . '.agiliumtrade.ai';

        try {
            $response = Http::withHeaders(['auth-token' => $token])
                ->get("{$baseUrl}/users/current/accounts/{$account->metaapi_account_id}/positions");

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
