<?php

namespace App\Services\MetaApi;

use App\Models\BrokerAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la Provisioning API de MetaApi.
 *
 * Aqui (lado Laravel) solo PROVISIONAMOS la cuenta: registramos las credenciales
 * del broker en MetaApi y la desplegamos en la nube. El TRADING en si lo hace el
 * worker de Python con el SDK, leyendo el metaapi_account_id que guardamos.
 *
 * Docs: https://metaapi.cloud/docs/provisioning/
 */
class MetaApiProvisioning
{
    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
    ) {
        $this->token = $token ?: config('services.metaapi.token');
        $this->baseUrl = rtrim($baseUrl ?: config('services.metaapi.provisioning_url'), '/');
    }

    protected function client(): PendingRequest
    {
        if (empty($this->token)) {
            throw new RuntimeException('METAAPI_TOKEN no esta configurado en el .env.');
        }

        return Http::withHeaders([
            'auth-token' => $this->token,
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl)->timeout(30);
    }

    /**
     * Crea la cuenta en MetaApi a partir de las credenciales del broker.
     * Devuelve el id de la cuenta MetaApi (accountId).
     *
     * Las credenciales NO se guardan en nuestra BD: se mandan aqui y MetaApi
     * las almacena cifradas.
     */
    public function createAccount(BrokerAccount $account, string $password): string
    {
        $payload = [
            'name' => $account->name,
            'type' => config('services.metaapi.account_type', 'cloud-g2'),
            'login' => (string) $account->login,
            'password' => $password,
            'server' => $account->server,
            'platform' => $account->platform, // mt4 | mt5
            'magic' => 0,
            'region' => $account->region ?: config('services.metaapi.region'),
            'reliability' => config('services.metaapi.reliability', 'high'),
            'application' => 'MetaApi',
        ];

        // MetaApi valida la conexion del broker de forma asincrona: hasta que
        // termina responde con error "AcceptedError" y un tiempo de reintento.
        // Reintentamos el mismo POST hasta que devuelve la cuenta creada.
        // (Esto corre dentro de un job en cola, por eso podemos dormir.)
        $maxAttempts = 8;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->client()->post('/users/current/accounts', $payload);

            if ($response->failed()) {
                throw new RuntimeException(
                    'MetaApi createAccount fallo ('.$response->status().'): '.$response->body()
                );
            }

            $data = $response->json() ?? [];

            if (($data['error'] ?? null) === 'AcceptedError') {
                if ($attempt === $maxAttempts) {
                    throw new RuntimeException(
                        'MetaApi: la validacion de conexion no termino a tiempo. '
                        .'Revisa login/server/password y reintenta.'
                    );
                }
                sleep(60);

                continue;
            }

            // El identificador canonico (deploy/get/list) es '_id'; algunas
            // respuestas solo traen 'id'. Preferimos '_id' si existe.
            $id = $data['_id'] ?? $data['id'] ?? null;

            if (! is_string($id) || $id === '') {
                throw new RuntimeException(
                    'MetaApi no devolvio un account id valido. Respuesta: '.$response->body()
                );
            }

            return $id;
        }

        throw new RuntimeException('MetaApi createAccount: sin respuesta valida.');
    }

    /**
     * Despliega la cuenta (arranca el terminal en la nube).
     */
    public function deploy(string $accountId): void
    {
        $response = $this->client()->post("/users/current/accounts/{$accountId}/deploy");

        if ($response->failed()) {
            throw new RuntimeException(
                'MetaApi deploy fallo ('.$response->status().'): '.$response->body()
            );
        }
    }

    /**
     * Apaga el terminal en la nube (deja de consumir recurso).
     */
    public function undeploy(string $accountId): void
    {
        $this->client()->post("/users/current/accounts/{$accountId}/undeploy");
    }

    /**
     * Elimina la cuenta de MetaApi por completo.
     */
    public function deleteAccount(string $accountId): void
    {
        $this->client()->delete("/users/current/accounts/{$accountId}");
    }

    /**
     * Estado actual de la cuenta en MetaApi.
     *
     * @return array<string, mixed>
     */
    public function getAccount(string $accountId): array
    {
        $response = $this->client()->get("/users/current/accounts/{$accountId}");

        if ($response->failed()) {
            throw new RuntimeException(
                'MetaApi getAccount fallo ('.$response->status().'): '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Provisiona de punta a punta: crea + despliega, y deja el BrokerAccount
     * actualizado con el id y el estado. Devuelve el account actualizado.
     */
    public function provision(BrokerAccount $account, string $password): BrokerAccount
    {
        try {
            $accountId = $this->createAccount($account, $password);
            $account->forceFill([
                'metaapi_account_id' => $accountId,
                'region' => $account->region ?: config('services.metaapi.region'),
                'provision_state' => 'deploying',
                'last_error' => null,
            ])->save();

            $this->deploy($accountId);

            $account->forceFill(['provision_state' => 'deployed'])->save();
        } catch (\Throwable $e) {
            $account->forceFill([
                'provision_state' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $account->refresh();
    }
}
