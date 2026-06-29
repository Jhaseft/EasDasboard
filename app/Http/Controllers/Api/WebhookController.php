<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteSignal;
use App\Models\BrokerAccount;
use App\Models\Signal;
use App\Services\Wallet\PlatformBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ingesta de señales externas por webhook (TradingView / MT5 / Python).
 *
 * La señal llega a POST /api/webhook/{token}. El token identifica la cuenta y
 * actúa como autenticación (no hay sesión). Formato JSON esperado:
 *
 *   {
 *     "action": "buy" | "sell" | "close",
 *     "symbol": "EURUSD",
 *     "volume": 0.10,          // requerido en buy/sell
 *     "sl": 1.0800,            // opcional
 *     "tp": 1.0900,            // opcional
 *     "id":  "abc-123"         // opcional, para idempotencia (TradingView reintenta)
 *   }
 */
class WebhookController extends Controller
{
    public function receive(Request $request, string $token, PlatformBilling $billing): JsonResponse
    {
        $account = BrokerAccount::with('user')->where('webhook_token', $token)->first();

        if (! $account || ! $account->is_enabled || ! $account->acceptsWebhook()) {
            // Mensaje genérico: no revelar si el token existe.
            return response()->json(['ok' => false, 'message' => 'Webhook inválido.'], 404);
        }

        // El webhook es un add-on de pago ($15/mes): requiere módulo activo.
        if (! $billing->hasWebhookModule($account->user)) {
            return response()->json([
                'ok' => false,
                'message' => 'El módulo webhook no está activo para esta cuenta.',
            ], 402);
        }

        $data = $request->validate([
            'action' => ['required', 'in:buy,sell,close'],
            'symbol' => ['required', 'string', 'max:50'],
            'volume' => ['nullable', 'numeric', 'min:0.01', 'required_if:action,buy,sell'],
            'sl'     => ['nullable', 'numeric'],
            'tp'     => ['nullable', 'numeric'],
            'id'     => ['nullable', 'string', 'max:255'],
        ]);

        $externalId = $data['id'] ?? null;

        // Idempotencia: si ya recibimos este external_id para esta cuenta, no repetir.
        if ($externalId !== null) {
            $existing = Signal::where('broker_account_id', $account->id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return response()->json([
                    'ok'        => true,
                    'duplicate' => true,
                    'signal_id' => $existing->id,
                ]);
            }
        }

        $signal = Signal::create([
            'broker_account_id' => $account->id,
            'source'            => 'webhook',
            'external_id'       => $externalId,
            'action'            => $data['action'],
            'symbol'            => $data['symbol'],
            'volume'            => $data['volume'] ?? null,
            'sl'                => $data['sl'] ?? null,
            'tp'                => $data['tp'] ?? null,
            'status'            => 'received',
            'payload'           => $request->all(),
        ]);

        ExecuteSignal::dispatch($signal);

        return response()->json([
            'ok'        => true,
            'signal_id' => $signal->id,
        ], 202);
    }
}
