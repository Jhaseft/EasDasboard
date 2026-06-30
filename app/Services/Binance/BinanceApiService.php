<?php

namespace App\Services\Binance;

use App\Exceptions\BinanceException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente HTTP de Binance (solo lectura). Su única tarea es consultar el historial
 * de depósitos de la cuenta, firmado con HMAC-SHA256. No conoce la BD ni la
 * billetera: la lógica de matcheo/acreditación vive en BinanceDepositService.
 * Port de la referencia NestJS (binance.service.ts).
 */
class BinanceApiService
{
    private const TIMEOUT_SECONDS = 15;
    private const MAX_LOOKBACK_MS = 90 * 24 * 60 * 60 * 1000; // tope 90 días (límite Binance)

    private readonly string $key;
    private readonly string $secret;
    private readonly string $base;
    private readonly string $coin;

    public function __construct()
    {
        $this->key = (string) config('services.binance.key');
        $this->secret = (string) config('services.binance.secret');
        $this->base = rtrim((string) config('services.binance.base', 'https://api.binance.com'), '/');
        $this->coin = (string) config('services.binance.coin', 'USDT');
    }

    public function isConfigured(): bool
    {
        return $this->key !== '' && $this->secret !== '';
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new BinanceException('Binance no está configurado (BINANCE_API_KEY / BINANCE_API_SECRET).');
        }
    }

    /**
     * Historial de depósitos USDT desde (earliest - lookback). Devuelve el array
     * crudo de Binance (cada elemento: amount, coin, network, status, txId, ...).
     */
    public function fetchDepositHistory(CarbonInterface $earliest, ?int $lookbackMs = null): array
    {
        $this->ensureConfigured();

        $lookbackMs ??= ((int) config('services.binance.history_lookback_minutes', 90)) * 60 * 1000;

        $startTime = max(
            $earliest->getTimestampMs() - $lookbackMs,
            now()->getTimestampMs() - self::MAX_LOOKBACK_MS,
        );

        // El orden importa: la firma se calcula sobre EXACTAMENTE este query string.
        $query = http_build_query([
            'coin'       => $this->coin,
            'startTime'  => $startTime,
            'timestamp'  => $this->binanceTimestamp(),
            'recvWindow' => 10000,
            'limit'      => 1000,
        ]);

        $signature = hash_hmac('sha256', $query, $this->secret);
        $url = "{$this->base}/sapi/v1/capital/deposit/hisrec?{$query}&signature={$signature}";

        try {
            $res = Http::withHeaders(['X-MBX-APIKEY' => $this->key])
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (Throwable $e) {
            Log::error('[binance] error de red al consultar depósitos', ['err' => $e->getMessage()]);
            throw new BinanceException('No se pudo contactar a Binance. Intenta más tarde.');
        }

        if ($res->failed()) {
            $body = $res->json();
            Log::error('[binance] API error', ['status' => $res->status(), 'body' => $body]);
            throw new BinanceException('Binance respondió un error: '.($body['msg'] ?? 'desconocido'));
        }

        $data = $res->json();

        return is_array($data) ? $data : [];
    }

    /**
     * Timestamp (ms) alineado con el reloj de Binance. Evita el error -1021
     * "Timestamp ahead of server time" cuando el reloj local está adelantado:
     * consulta la hora del servidor (endpoint público) y cachea el offset.
     */
    private function binanceTimestamp(): int
    {
        $offset = Cache::remember('binance:time_offset', now()->addMinutes(5), function () {
            try {
                $res = Http::timeout(10)->get("{$this->base}/api/v3/time");
                if ($res->ok() && isset($res->json()['serverTime'])) {
                    return (int) $res->json()['serverTime'] - now()->getTimestampMs();
                }
            } catch (Throwable $e) {
                Log::warning('[binance] no se pudo sincronizar la hora', ['err' => $e->getMessage()]);
            }

            return 0;
        });

        return now()->getTimestampMs() + (int) $offset;
    }

    /**
     * Un depósito está disponible para acreditar: 1 success o 6 credited.
     */
    public function isDepositSuccess(array $dep): bool
    {
        $status = $dep['status'] ?? null;

        return $status === 1 || $status === 6;
    }

    /**
     * ¿El monto recibido cae dentro de la tolerancia respecto al esperado?
     * underpay (default 0.5%) por fees de red, overpay (default 5%) frena sobrepagos.
     */
    public function amountMatches(float $expected, string|float $received): bool
    {
        if ($expected <= 0) {
            return false;
        }

        $underpay = (float) config('services.binance.underpay_tolerance', 0.5);
        $overpay = (float) config('services.binance.overpay_tolerance', 5);

        $min = $expected * (100 - $underpay) / 100;
        $max = $expected * (100 + $overpay) / 100;
        $rec = (float) $received;

        return $rec >= $min && $rec <= $max;
    }

    public function coin(): string
    {
        return $this->coin;
    }
}
