<?php

namespace App\Services\MetaApi;

use App\Models\BrokerAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la MetaStats API de MetaApi: estadísticas AUDITADAS calculadas por
 * MetaApi directamente desde el historial real del bróker (no editables por el
 * usuario). Es lo que da confianza en el marketplace (estilo MyFxBook).
 *
 * Docs: https://metaapi.cloud/docs/metastats/
 */
class MetaStats
{
    public function __construct(protected ?string $token = null)
    {
        $this->token = $token ?: config('services.metaapi.token');
    }

    protected function client(?string $region): PendingRequest
    {
        if (empty($this->token)) {
            throw new RuntimeException('METAAPI_TOKEN no está configurado.');
        }

        $region = $region ?: config('services.metaapi.region', 'new-york');

        return Http::withHeaders([
            'auth-token' => $this->token,
            'Accept'     => 'application/json',
        ])->baseUrl("https://metastats-api-v1.{$region}.agiliumtrade.ai")->timeout(25);
    }

    /**
     * Métricas + operaciones recientes de una cuenta, normalizadas y listas para
     * mostrar. Respeta el modo incógnito (oculta $ y deja solo %). Cacheado 5 min
     * porque la llamada externa es costosa. Devuelve null si MetaApi falla.
     *
     * @return array{metrics: array<string,mixed>, trades: array<int,array<string,mixed>>}|null
     */
    public function publicStats(BrokerAccount $account, ?int $tradesLimit = 25): ?array
    {
        if (! $account->metaapi_account_id) {
            return null;
        }

        $incognito = ! $account->show_balance;
        $cacheKey = "metastats:public:{$account->id}:".($incognito ? 'inc' : 'full');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($account, $incognito, $tradesLimit) {
            try {
                $metrics = $this->rawMetrics($account);
                if ($metrics === null) {
                    return null;
                }

                return [
                    'metrics' => $this->normalizeMetrics($metrics, $incognito),
                    'trades'  => $this->normalizeTrades($this->rawTrades($account, $tradesLimit), $incognito),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function rawMetrics(BrokerAccount $account): ?array
    {
        $resp = $this->client($account->region)
            ->get("/users/current/accounts/{$account->metaapi_account_id}/metrics");

        return $resp->successful() ? ($resp->json('metrics') ?? null) : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function rawTrades(BrokerAccount $account, ?int $limit): array
    {
        // Historial completo (desde 2015 hasta hoy); MetaStats lo pagina por fecha.
        // Las fechas van en el PATH y llevan espacios -> hay que URL-encodearlas.
        $start = rawurlencode('2015-01-01 00:00:00.000');
        $end = rawurlencode(now()->addDay()->format('Y-m-d H:i:s').'.000');

        $resp = $this->client($account->region)
            ->get("/users/current/accounts/{$account->metaapi_account_id}/historical-trades/{$start}/{$end}", [
                'limit' => $limit,
            ]);

        // La MetaStats API devuelve la lista bajo la clave 'trades'.
        $trades = $resp->successful() ? ($resp->json('trades') ?? []) : [];

        // Solo operaciones reales de mercado: descartar depósitos/retiros/balance.
        $trades = array_values(array_filter($trades, function ($t) {
            $type = $t['type'] ?? '';
            return in_array($type, ['DEAL_TYPE_BUY', 'DEAL_TYPE_SELL'], true) && ! empty($t['symbol']);
        }));

        // Más recientes primero.
        usort($trades, fn ($a, $b) => strcmp($b['closeTime'] ?? '', $a['closeTime'] ?? ''));

        return $limit ? array_slice($trades, 0, $limit) : $trades;
    }

    /**
     * @param  array<string,mixed>  $m
     * @return array<string,mixed>
     */
    protected function normalizeMetrics(array $m, bool $incognito): array
    {
        $out = [
            'gain'             => $m['gain'] ?? null,           // % total
            'monthly_gain'     => $m['monthlyGain'] ?? null,    // %
            'max_drawdown'     => $m['maxDrawdown'] ?? null,    // %
            'profit_factor'    => $m['profitFactor'] ?? null,
            'win_rate'         => $m['wonTradesPercent'] ?? null,
            'trades'           => $m['trades'] ?? null,
            'sharpe'           => $m['sharpeRatio'] ?? null,
            'incognito'        => $incognito,
        ];

        // Solo mostramos cifras en dólares si NO está en modo incógnito.
        if (! $incognito) {
            $out['balance'] = $m['balance'] ?? null;
            $out['equity'] = $m['equity'] ?? null;
            $out['profit'] = $m['profit'] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<int,array<string,mixed>>  $trades
     * @return array<int,array<string,mixed>>
     */
    protected function normalizeTrades(array $trades, bool $incognito): array
    {
        return array_map(function ($t) use ($incognito) {
            $type = match ($t['type'] ?? '') {
                'DEAL_TYPE_BUY' => 'buy',
                'DEAL_TYPE_SELL' => 'sell',
                default => strtolower(str_replace('DEAL_TYPE_', '', $t['type'] ?? '')),
            };

            $row = [
                'symbol'     => $t['symbol'] ?? null,
                'type'       => $type,
                'volume'     => $t['volume'] ?? null,
                'gain'       => $t['gain'] ?? null,        // % del trade
                'success'    => $t['success'] ?? null,     // 'won' | 'lost'
                'open_time'  => $t['openTime'] ?? null,
                'close_time' => $t['closeTime'] ?? null,
            ];

            // El profit en $ solo si no es incógnito.
            if (! $incognito) {
                $row['profit'] = $t['profit'] ?? null;
            }

            return $row;
        }, $trades);
    }
}
