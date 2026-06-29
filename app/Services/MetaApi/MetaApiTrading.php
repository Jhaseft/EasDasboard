<?php

namespace App\Services\MetaApi;

use App\Models\BrokerAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la MetaApi Client API (trading REST).
 *
 * Sirve para EJECUTAR órdenes desde el lado Laravel sin pasar por el worker:
 * lo usa la ingesta por webhook (ExecuteSignal) para abrir/cerrar en la cuenta
 * dueña de la señal. La copia a las esclavas la hace el worker por streaming.
 *
 * Docs: https://metaapi.cloud/docs/client/restApi/api/trade/
 */
class MetaApiTrading
{
    public function __construct(
        protected ?string $token = null,
    ) {
        $this->token = $token ?: config('services.metaapi.token');
    }

    protected function baseUrl(?string $region): string
    {
        $region = $region ?: config('services.metaapi.region', 'new-york');

        return "https://mt-client-api-v1.{$region}.agiliumtrade.ai";
    }

    protected function client(?string $region): PendingRequest
    {
        if (empty($this->token)) {
            throw new RuntimeException('METAAPI_TOKEN no está configurado en el .env.');
        }

        return Http::withHeaders([
            'auth-token'   => $this->token,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->baseUrl($this->baseUrl($region))->timeout(30);
    }

    /**
     * Abre una posición de mercado en la cuenta. Devuelve el positionId.
     */
    public function openPosition(
        BrokerAccount $account,
        string $symbol,
        string $direction,
        float $volume,
        ?float $sl = null,
        ?float $tp = null,
        ?string $comment = null,
    ): string {
        $payload = [
            'actionType' => $direction === 'sell' ? 'ORDER_TYPE_SELL' : 'ORDER_TYPE_BUY',
            'symbol'     => $symbol,
            'volume'     => $volume,
        ];
        if ($sl !== null) {
            $payload['stopLoss'] = $sl;
        }
        if ($tp !== null) {
            $payload['takeProfit'] = $tp;
        }
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }

        $response = $this->client($account->region)
            ->post("/users/current/accounts/{$account->metaapi_account_id}/trade", $payload);

        if ($response->failed()) {
            throw new RuntimeException('MetaApi trade (open) falló: '.$response->body());
        }

        return $response->json('positionId') ?? $response->json('orderId') ?? 'unknown';
    }

    /**
     * Cierra todas las posiciones abiertas de un símbolo en la cuenta.
     * Devuelve cuántas cerró.
     */
    public function closePositionsBySymbol(BrokerAccount $account, string $symbol): int
    {
        $positions = $this->getPositions($account);
        $closed = 0;

        foreach ($positions as $position) {
            if (($position['symbol'] ?? null) !== $symbol) {
                continue;
            }

            $response = $this->client($account->region)
                ->post("/users/current/accounts/{$account->metaapi_account_id}/trade", [
                    'actionType' => 'POSITION_CLOSE_ID',
                    'positionId' => (string) $position['id'],
                ]);

            if ($response->successful()) {
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * Posiciones abiertas de la cuenta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPositions(BrokerAccount $account): array
    {
        $response = $this->client($account->region)
            ->get("/users/current/accounts/{$account->metaapi_account_id}/positions");

        return $response->successful() ? ($response->json() ?? []) : [];
    }
}
