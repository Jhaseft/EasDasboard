<?php

namespace App\Services\Binance;

use App\Exceptions\BinanceException;
use App\Models\BinanceDepositIntent;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lógica de negocio de la recarga con USDT (Binance). Crea "intents" de depósito,
 * matchea los depósitos reales del historial de Binance contra los intents
 * pendientes (por monto+ventana, o por TXID manual) y acredita la billetera en USD
 * (1 USDT = 1 USD) reutilizando WalletService.
 */
class BinanceDepositService
{
    public function __construct(
        private readonly BinanceApiService $api,
        private readonly WalletService $wallets,
    ) {}

    // ─── Config helpers ──────────────────────────────────────────────────────

    private function networks(): array
    {
        return (array) config('services.binance.networks', []);
    }

    private function resolveNetwork(?string $network): array
    {
        $networks = $this->networks();
        if (empty($networks)) {
            throw new BinanceException('No hay redes de Binance configuradas.');
        }

        $key = $network ? strtoupper($network) : (string) config('services.binance.default_network');

        foreach ($networks as $n) {
            if (strtoupper($n['network']) === $key) {
                return $n;
            }
        }

        // Si la red pedida no existe, cae a la primera disponible.
        return $networks[0];
    }

    private function ttlMinutes(): int
    {
        return (int) config('services.binance.intent_ttl_minutes', 30);
    }

    // ─── API pública ─────────────────────────────────────────────────────────

    /**
     * Crea (o reutiliza) un intent de depósito y devuelve los datos para mostrar.
     */
    public function createIntent(User $user, float $amountUsd, ?string $network = null): array
    {
        $wallet = $this->wallets->walletFor($user);
        $net = $this->resolveNetwork($network);
        $amountUsd = round($amountUsd, 2);
        $coin = $this->api->coin();

        // Reutiliza un pendiente vigente del mismo usuario/red/monto.
        $existing = BinanceDepositIntent::query()
            ->pendingActive()
            ->where('user_id', $user->id)
            ->where('network', $net['network'])
            ->where('amount_usd', $amountUsd)
            ->latest()
            ->first();

        if ($existing) {
            return $this->toPayload($existing);
        }

        $intent = BinanceDepositIntent::create([
            'user_id'        => $user->id,
            'wallet_id'      => $wallet->id,
            'network'        => $net['network'],
            'coin'           => $coin,
            'wallet_address' => $net['wallet'],
            'amount_usd'     => $amountUsd,
            'expected_usdt'  => $amountUsd, // 1 USDT = 1 USD
            'status'         => BinanceDepositIntent::STATUS_PENDING,
            'expires_at'     => now()->addMinutes($this->ttlMinutes()),
        ]);

        Log::info("[binance] intent creado id={$intent->id} user={$user->id} {$amountUsd} USDT net={$net['network']}");

        return $this->toPayload($intent);
    }

    /**
     * Estado del intent para el polling. Si sigue pendiente, intenta matchear un
     * depósito real y acreditar. Devuelve PENDING|CONFIRMED|EXPIRED|REJECTED.
     */
    public function getStatus(User $user, int $intentId): array
    {
        $intent = $this->findUserIntent($user, $intentId);

        if ($intent->status !== BinanceDepositIntent::STATUS_PENDING) {
            return $this->statusPayload($intent);
        }

        if ($intent->isExpired()) {
            $this->markExpired($intent);

            return $this->statusPayload($intent->fresh());
        }

        // Intenta matchear contra el historial real de Binance.
        try {
            $this->matchPendingIntent($intent);
        } catch (BinanceException $e) {
            // Error transitorio: el frontend reintentará en el próximo poll.
            Log::warning("[binance] match falló intent={$intent->id}: {$e->getMessage()}");
        }

        return $this->statusPayload($intent->fresh());
    }

    /**
     * Confirmación manual con TXID: busca ese hash en Binance (ventana ancha) y
     * acredita si coincide con el intent.
     */
    public function confirmWithTxid(User $user, int $intentId, string $txid): array
    {
        $txid = trim($txid);
        if ($txid === '') {
            throw new BinanceException('El TXID es obligatorio.');
        }

        $intent = $this->findUserIntent($user, $intentId);

        if ($intent->status === BinanceDepositIntent::STATUS_CONFIRMED) {
            return $this->statusPayload($intent);
        }
        if ($intent->isExpired()) {
            $this->markExpired($intent);
            throw new BinanceException('Este intento expiró. Crea uno nuevo.');
        }

        // ¿TXID ya usado por otro intent?
        $collision = BinanceDepositIntent::where('txid', $txid)->first();
        if ($collision && $collision->id !== $intent->id) {
            throw new BinanceException('Este TXID ya fue usado en otra recarga.');
        }

        $lookbackMs = ((int) config('services.binance.manual_lookback_minutes', 1440)) * 60 * 1000;
        $deposits = $this->api->fetchDepositHistory($intent->created_at, $lookbackMs);

        $needle = strtolower($txid);
        $match = null;
        foreach ($deposits as $dep) {
            $depTxid = strtolower(trim($dep['txId'] ?? ''));
            if ($depTxid === '') {
                continue;
            }
            if (strtoupper($dep['coin'] ?? '') !== strtoupper($intent->coin)) {
                continue;
            }
            // Exacto, u off-chain (substring) como en la referencia.
            if ($depTxid === $needle
                || (strlen($needle) >= 10 && str_contains($depTxid, $needle))
                || (strlen($depTxid) >= 10 && str_contains($needle, $depTxid))) {
                $match = $dep;
                break;
            }
        }

        if (! $match) {
            throw new BinanceException('El TXID aún no aparece en Binance. Espera a que la red confirme y reintenta.');
        }

        $this->applyMatch($intent, $match, $txid);

        return $this->statusPayload($intent->fresh());
    }

    /**
     * Matchea los intents pendientes contra el historial (usado por polling y cron).
     * Acredita los que tengan exactamente una coincidencia válida.
     *
     * @param  BinanceDepositIntent|null  $only  si se pasa, solo intenta ese intent
     */
    public function matchPendingIntent(?BinanceDepositIntent $only = null): void
    {
        $pending = BinanceDepositIntent::query()
            ->pendingActive()
            ->whereNull('txid')
            ->when($only, fn ($q) => $q->whereKey($only->id))
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $earliest = $pending->min('created_at');
        $deposits = $this->api->fetchDepositHistory(Carbon::parse($earliest));

        $usedTxids = BinanceDepositIntent::whereNotNull('txid')
            ->pluck('txid')
            ->map(fn ($t) => strtolower($t))
            ->flip();

        foreach ($deposits as $dep) {
            $txid = $dep['txId'] ?? null;
            if (! $txid || $usedTxids->has(strtolower($txid))) {
                continue;
            }
            if (! $this->api->isDepositSuccess($dep)) {
                continue;
            }

            $depTimeMs = (int) ($dep['insertTime'] ?? 0);

            $candidates = $pending->filter(function (BinanceDepositIntent $i) use ($dep, $depTimeMs) {
                return strtoupper($i->coin) === strtoupper($dep['coin'] ?? '')
                    && $this->api->amountMatches((float) $i->expected_usdt, $dep['amount'] ?? '0')
                    && $depTimeMs >= ($i->created_at->getTimestampMs() - 60_000);
            });

            // Solo acredita si hay UNA coincidencia (sin ambigüedad).
            if ($candidates->count() !== 1) {
                continue;
            }

            try {
                $this->applyMatch($candidates->first(), $dep, $txid);
                $usedTxids->put(strtolower($txid), true);
            } catch (\Throwable $e) {
                Log::error("[binance] error acreditando intent={$candidates->first()->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Marca como expirados los intents pendientes vencidos (lo usa el cron).
     */
    public function expireOverdue(): int
    {
        return BinanceDepositIntent::where('status', BinanceDepositIntent::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => BinanceDepositIntent::STATUS_EXPIRED]);
    }

    // ─── Internos ────────────────────────────────────────────────────────────

    /**
     * Acredita el depósito en la billetera. Idempotente vía atomic claim + txid unique.
     */
    private function applyMatch(BinanceDepositIntent $intent, array $dep, string $txid): void
    {
        // Validación de monto por si el match vino del TXID manual.
        if (! $this->api->amountMatches((float) $intent->expected_usdt, $dep['amount'] ?? '0')) {
            $intent->update([
                'status'         => BinanceDepositIntent::STATUS_REJECTED,
                'failure_reason' => "Monto recibido ({$dep['amount']}) no coincide con el esperado ({$intent->expected_usdt}).",
                'binance_data'   => $dep,
            ]);
            throw new BinanceException("El monto recibido ({$dep['amount']} {$intent->coin}) no coincide con el esperado. Contacta a soporte.");
        }

        // Atomic claim: solo el primero pasa pending -> confirmed y fija el txid.
        $claimed = BinanceDepositIntent::where('id', $intent->id)
            ->where('status', BinanceDepositIntent::STATUS_PENDING)
            ->update([
                'status'       => BinanceDepositIntent::STATUS_CONFIRMED,
                'txid'         => $txid,
                'confirmed_at' => now(),
                'binance_data' => json_encode($dep),
            ]);

        if ($claimed === 0) {
            return; // ya fue acreditado por otra vía (polling/cron/manual)
        }

        $intent->refresh();
        $wallet = $intent->wallet;

        DB::transaction(function () use ($intent, $wallet, $txid, $dep) {
            $tx = $this->wallets->deposit(
                $wallet,
                (float) $intent->amount_usd,
                'Recarga USDT Binance',
                ['txid' => $txid, 'network' => $intent->network, 'received' => $dep['amount'] ?? null],
            );

            $intent->update(['wallet_transaction_id' => $tx->id]);
        });

        Log::info("[binance] acreditado +{$intent->amount_usd} USD a user={$intent->user_id} txid={$txid}");
    }

    private function markExpired(BinanceDepositIntent $intent): void
    {
        BinanceDepositIntent::where('id', $intent->id)
            ->where('status', BinanceDepositIntent::STATUS_PENDING)
            ->update(['status' => BinanceDepositIntent::STATUS_EXPIRED]);
    }

    private function findUserIntent(User $user, int $intentId): BinanceDepositIntent
    {
        $intent = BinanceDepositIntent::find($intentId);

        if (! $intent || $intent->user_id !== $user->id) {
            abort(404, 'Intento no encontrado.');
        }

        return $intent;
    }

    /**
     * Payload completo para la página (incluye datos del intent + redes disponibles).
     */
    private function toPayload(BinanceDepositIntent $intent): array
    {
        return [
            'intentId'      => $intent->id,
            'network'       => $intent->network,
            'coin'          => $intent->coin,
            'walletAddress' => $intent->wallet_address,
            'amountUsd'     => (float) $intent->amount_usd,
            'expectedUsdt'  => (float) $intent->expected_usdt,
            'status'        => $this->mapStatus($intent->status),
            'expiresAt'     => $intent->expires_at->toIso8601String(),
            'networks'      => array_map(fn ($n) => [
                'network' => $n['network'],
                'label'   => $n['label'],
            ], $this->networks()),
        ];
    }

    /**
     * Payload reducido para el polling de estado.
     */
    private function statusPayload(BinanceDepositIntent $intent): array
    {
        return [
            'status'    => $this->mapStatus($intent->status),
            'intentId'  => $intent->id,
            'amountUsd' => (float) $intent->amount_usd,
            'reason'    => $intent->failure_reason,
        ];
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
