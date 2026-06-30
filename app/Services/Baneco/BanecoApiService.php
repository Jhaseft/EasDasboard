<?php

namespace App\Services\Baneco;

use App\Exceptions\BanecoException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente HTTP puro de la API "QR Simple" de Banco Económico. No conoce la BD ni
 * la billetera: solo encripta, autentica y opera QRs. La lógica de negocio vive
 * en BanecoQrService. Port de la referencia NestJS (baneco-api.service.ts).
 */
class BanecoApiService
{
    private const TIMEOUT_SECONDS = 12;
    private const TOKEN_CACHE_KEY = 'baneco:token';
    private const TOKEN_TTL_MINUTES = 14; // el token del banco dura ~15 min

    private readonly string $base;
    private readonly string $aesKey;
    private readonly string $username;
    private readonly string $password;
    private readonly string $accountCredit;
    private readonly string $currency;

    public function __construct()
    {
        $this->base = $this->cfg('base');
        $this->aesKey = $this->cfg('aes_key');
        $this->username = $this->cfg('username');
        $this->password = $this->cfg('password');
        $this->accountCredit = $this->cfg('account_credit');
        $this->currency = strtoupper($this->cfg('currency', 'BOB')) === 'USD' ? 'USD' : 'BOB';
    }

    private function cfg(string $key, string $default = ''): string
    {
        $raw = (string) (config("services.baneco.$key") ?? $default);

        return preg_replace('/[\r\n]+/', '', trim($raw));
    }

    private function ensureConfigured(): void
    {
        if (! $this->base || ! $this->aesKey || ! $this->username || ! $this->password || ! $this->accountCredit) {
            Log::error('[baneco] configuración incompleta', [
                'base' => (bool) $this->base, 'aesKey' => (bool) $this->aesKey,
                'user' => (bool) $this->username, 'pass' => (bool) $this->password,
                'account' => (bool) $this->accountCredit,
            ]);
            throw new BanecoException('El pago con QR no está configurado. Intenta más tarde.');
        }
    }

    private function reqId(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 7);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->base)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson();
    }

    /**
     * Pide al banco que cifre (AES) un texto. Se usa para el password y la cuenta.
     */
    public function encrypt(string $text): string
    {
        $this->ensureConfigured();

        try {
            $res = $this->client()->get('/api/authentication/encrypt', [
                'text' => $text,
                'aesKey' => $this->aesKey,
            ]);
        } catch (Throwable $e) {
            Log::error('[baneco][encrypt] error de red', ['err' => $e->getMessage()]);
            throw new BanecoException('No se pudo contactar al banco. Intenta más tarde.');
        }

        if ($res->failed()) {
            Log::error('[baneco][encrypt] HTTP '.$res->status());
            throw new BanecoException('No se pudo preparar el pago con QR.');
        }

        // El endpoint devuelve el texto cifrado como string JSON ("...").
        return trim($res->body(), "\" \t\n\r");
    }

    private function login(string $reqId): string
    {
        $this->ensureConfigured();
        Log::info("[baneco][$reqId] autenticando");

        $encPass = $this->encrypt($this->password);

        try {
            $res = $this->client()->post('/api/authentication/authenticate', [
                'userName' => $this->username,
                'password' => $encPass,
            ]);
        } catch (Throwable $e) {
            Log::error("[baneco][$reqId] login error de red", ['err' => $e->getMessage()]);
            throw new BanecoException('No se pudo contactar al banco. Intenta más tarde.');
        }

        if ($res->status() === 401 || $res->status() === 403) {
            Log::error("[baneco][$reqId] login no autorizado status=".$res->status());
            throw new BanecoException('No se pudo generar el QR en este momento. Intenta nuevamente en unos minutos.');
        }

        $data = $res->json();
        if (! is_array($data) || ($data['responseCode'] ?? null) !== 0 || empty($data['token'])) {
            Log::error("[baneco][$reqId] login falló", [
                'responseCode' => $data['responseCode'] ?? null,
                'message' => $data['message'] ?? 'sin detalle',
            ]);
            throw new BanecoException('No se pudo autenticar con el banco. Intenta más tarde.');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $data['token'], now()->addMinutes(self::TOKEN_TTL_MINUTES));
        Log::info("[baneco][$reqId] token obtenido");

        return $data['token'];
    }

    private function token(string $reqId): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->login($reqId);
    }

    private function invalidateToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    /**
     * Llamada autenticada con reintento único si el token expiró (401).
     *
     * @param  'GET'|'POST'|'DELETE'  $method
     */
    private function authedCall(string $method, string $path, array $body = [], ?string $reqId = null, bool $tolerateError = false): array
    {
        $this->ensureConfigured();
        $reqId ??= $this->reqId();

        $attempt = function (string $token) use ($method, $path, $body): array {
            $req = $this->client()->withToken($token);
            // statusQR/cancelQR mandan el cuerpo JSON incluso en GET/DELETE.
            $options = $body ? ['json' => $body] : [];

            $res = $req->send($method, $path, $options);

            return ['status' => $res->status(), 'json' => $res->json(), 'ok' => $res->successful()];
        };

        try {
            $token = $this->token($reqId);
            $r = $attempt($token);

            // 401 → refresca token y reintenta una vez.
            if ($r['status'] === 401 || $r['status'] === 403) {
                Log::warning("[baneco][$reqId] 401 en $path, refrescando token y reintentando");
                $this->invalidateToken();
                $r = $attempt($this->login($reqId));
            }
        } catch (BanecoException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("[baneco][$reqId] $path error de red", ['err' => $e->getMessage()]);
            throw new BanecoException('No se pudo contactar al banco. Intenta más tarde.');
        }

        $data = is_array($r['json']) ? $r['json'] : [];

        if (($data['responseCode'] ?? null) !== 0 && ! $tolerateError) {
            Log::error("[baneco][$reqId] $path falló", [
                'status' => $r['status'],
                'responseCode' => $data['responseCode'] ?? null,
                'message' => $data['message'] ?? 'sin detalle',
            ]);
            throw new BanecoException('No se pudo procesar la operación con el banco. Intenta más tarde.');
        }

        Log::info("[baneco][$reqId] $path ok");

        return $data;
    }

    /**
     * Genera un QR de cobro. amount va en la moneda indicada (BOB por defecto).
     */
    public function generateQR(array $params): array
    {
        $reqId = $params['reqId'] ?? $this->reqId();
        $accountEnc = $this->encrypt($this->accountCredit);

        Log::info("[baneco][$reqId] generateQR", [
            'transactionId' => $params['transactionId'] ?? null,
            'amount' => $params['amount'] ?? null,
            'currency' => $params['currency'] ?? $this->currency,
        ]);

        return $this->authedCall('POST', '/api/qrsimple/generateQR', [
            'transactionId' => $params['transactionId'],
            'accountCredit' => $accountEnc,
            'currency' => $params['currency'] ?? $this->currency,
            'amount' => round((float) $params['amount'], 2),
            'description' => $params['description'] ?? '',
            'dueDate' => $params['dueDate'],
            'singleUse' => $params['singleUse'] ?? true,
            'modifyAmount' => $params['modifyAmount'] ?? false,
        ], $reqId);
    }

    /**
     * Consulta el estado de un QR. statusQrCode: 0 pendiente, 1 pagado, 9 anulado.
     */
    public function statusQR(string $qrId, ?string $reqId = null): array
    {
        return $this->authedCall('GET', '/api/qrsimple/statusQR', ['qrId' => $qrId], $reqId);
    }

    /**
     * Anula un QR pendiente. Tolera error porque el banco puede responder
     * "ya pagado" (lo interpreta el servicio de negocio).
     */
    public function cancelQR(string $qrId, ?string $reqId = null): array
    {
        return $this->authedCall('DELETE', '/api/qrsimple/cancelQR', ['qrId' => $qrId], $reqId, tolerateError: true);
    }
}
