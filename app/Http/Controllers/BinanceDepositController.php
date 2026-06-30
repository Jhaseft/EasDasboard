<?php

namespace App\Http\Controllers;

use App\Exceptions\BinanceException;
use App\Models\BinanceDepositIntent;
use App\Services\Binance\BinanceDepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recarga de billetera con USDT (Binance). store crea un intent y redirige a su
 * página (Wallet/BinanceDeposit), que muestra la dirección y hace polling de
 * /status. El usuario también puede confirmar pegando su TXID. No hay cancelación:
 * el intent expira solo por TTL.
 */
class BinanceDepositController extends Controller
{
    public function __construct(
        protected BinanceDepositService $binance,
    ) {}

    /**
     * Crea un intent de depósito y redirige a su página.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:1', 'max:100000'],
            'network' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->binance->createIntent(
                $request->user(),
                (float) $data['amount'],
                $data['network'] ?? null,
            );
        } catch (BinanceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('wallet.binance.show', $result['intentId']);
    }

    /**
     * Página de depósito: dirección, monto USDT, redes, expiración y polling.
     */
    public function show(Request $request, BinanceDepositIntent $intent): Response
    {
        $this->authorizeIntent($request, $intent);

        // Estado inicial desde la BD (sin llamar a Binance aquí; el polling lo hará).
        return Inertia::render('Wallet/BinanceDeposit', [
            'intent' => [
                'intentId'      => $intent->id,
                'network'       => $intent->network,
                'coin'          => $intent->coin,
                'walletAddress' => $intent->wallet_address,
                'amountUsd'     => (float) $intent->amount_usd,
                'expectedUsdt'  => (float) $intent->expected_usdt,
                'status'        => $this->mapStatus($intent->status),
                'expiresAt'     => $intent->expires_at->toIso8601String(),
            ],
            'networks' => array_map(fn ($n) => [
                'network' => $n['network'],
                'label'   => $n['label'],
            ], (array) config('services.binance.networks', [])),
        ]);
    }

    /**
     * Estado del intent para el polling (consulta Binance si sigue pendiente).
     */
    public function status(Request $request, BinanceDepositIntent $intent): JsonResponse
    {
        $this->authorizeIntent($request, $intent);

        try {
            $result = $this->binance->getStatus($request->user(), $intent->id);
        } catch (BinanceException $e) {
            return response()->json(['status' => 'PENDING', 'message' => $e->getMessage()], 200);
        }

        return response()->json($result);
    }

    /**
     * Confirmación manual con TXID.
     */
    public function confirm(Request $request, BinanceDepositIntent $intent): JsonResponse
    {
        $this->authorizeIntent($request, $intent);

        $data = $request->validate([
            'txid' => ['required', 'string', 'min:6', 'max:200'],
        ]);

        try {
            $result = $this->binance->confirmWithTxid($request->user(), $intent->id, $data['txid']);
        } catch (BinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    private function authorizeIntent(Request $request, BinanceDepositIntent $intent): void
    {
        abort_unless($intent->user_id === $request->user()->id, 404, 'Intento no encontrado.');
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            BinanceDepositIntent::STATUS_CONFIRMED => 'CONFIRMED',
            BinanceDepositIntent::STATUS_PENDING   => 'PENDING',
            BinanceDepositIntent::STATUS_REJECTED  => 'REJECTED',
            default                                => 'EXPIRED',
        };
    }
}
