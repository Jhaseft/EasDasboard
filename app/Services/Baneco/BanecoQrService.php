<?php

namespace App\Services\Baneco;

use App\Models\QrDeposit;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lógica de negocio de la recarga por QR de Banco Económico. Convierte el monto
 * USD que pide el usuario al equivalente en BOB que cobra el QR, y al confirmarse
 * el pago acredita el USD en la billetera reutilizando WalletService.
 */
class BanecoQrService
{
    public function __construct(
        private readonly BanecoApiService $api,
        private readonly WalletService $wallets,
    ) {}

    private function usdToBob(): float
    {
        // Editable por el administrador desde la tabla system_config (no el .env).
        return SystemConfig::usdToBob();
    }

    private function currency(): string
    {
        return strtoupper((string) config('services.baneco.currency', 'BOB')) === 'USD' ? 'USD' : 'BOB';
    }

    private function buildDueDate(): string
    {
        $days = (int) config('services.baneco.qr_ttl_days', 1);

        return now()->addDays(max(1, $days))->toDateString();
    }

    /**
     * Crea una recarga pendiente y genera el QR en Baneco.
     */
    public function createDeposit(User $user, float $amountUsd): array
    {
        $wallet = $this->wallets->walletFor($user);

        $rate = $this->usdToBob();
        $amountUsd = round($amountUsd, 2);
        $amountBob = round($amountUsd * $rate, 2);
        $dueDate = $this->buildDueDate();
        $reqId = Str::random(7);

        $deposit = QrDeposit::create([
            'user_id'        => $user->id,
            'wallet_id'      => $wallet->id,
            'transaction_id' => (string) Str::uuid(),
            'amount_usd'     => $amountUsd,
            'amount_bob'     => $amountBob,
            'exchange_rate'  => $rate,
            'currency'       => $this->currency(),
            'status'         => QrDeposit::STATUS_PENDING,
            'due_date'       => $dueDate,
        ]);

        try {
            $qr = $this->api->generateQR([
                'transactionId' => $deposit->transaction_id,
                'amount'        => $amountBob,
                'currency'      => $this->currency(),
                'description'   => 'Recarga billetera $'.number_format($amountUsd, 2).' USD',
                'dueDate'       => $dueDate,
                'singleUse'     => true,
                'modifyAmount'  => false,
                'reqId'         => $reqId,
            ]);
        } catch (\Throwable $e) {
            $deposit->update(['status' => QrDeposit::STATUS_FAILED]);
            Log::error("[baneco][$reqId] generateQR falló depositId={$deposit->id}", ['err' => $e->getMessage()]);
            throw $e;
        }

        $deposit->update([
            'qr_id'    => $qr['qrId'],
            'qr_image' => $qr['qrImage'] ?? null,
        ]);

        Log::info("[baneco][$reqId] QR creado depositId={$deposit->id} qrId={$qr['qrId']}");

        return [
            'qrId'      => $qr['qrId'],
            'qrImage'   => $qr['qrImage'] ?? null,
            'amountUsd' => $amountUsd,
            'amountBob' => $amountBob,
            'currency'  => $this->currency(),
            'rate'      => $rate,
            'dueDate'   => $dueDate,
        ];
    }

    /**
     * Estado del QR para el polling del frontend. Devuelve PENDING|PAID|CANCELED.
     */
    public function getStatus(User $user, string $qrId): array
    {
        $deposit = $this->findUserDeposit($user, $qrId);

        if ($deposit->status === QrDeposit::STATUS_PAID) {
            return ['status' => 'PAID', 'depositId' => $deposit->id];
        }
        if (in_array($deposit->status, [QrDeposit::STATUS_CANCELED, QrDeposit::STATUS_EXPIRED], true)) {
            return ['status' => 'CANCELED', 'depositId' => $deposit->id];
        }

        $remote = $this->api->statusQR($qrId);
        $code = $remote['statusQrCode'] ?? 0;

        if ($code === 1) {
            $this->applyPayment($qrId, $remote['payment'] ?? null);

            return ['status' => 'PAID', 'depositId' => $deposit->id];
        }
        if ($code === 9) {
            QrDeposit::where('id', $deposit->id)
                ->where('status', QrDeposit::STATUS_PENDING)
                ->update(['status' => QrDeposit::STATUS_CANCELED]);

            return ['status' => 'CANCELED', 'depositId' => $deposit->id];
        }

        return ['status' => 'PENDING', 'depositId' => $deposit->id];
    }

    /**
     * Anula un QR pendiente. Si el banco responde "ya pagado", lo acredita.
     */
    public function cancel(User $user, string $qrId): array
    {
        $deposit = $this->findUserDeposit($user, $qrId);

        if ($deposit->status !== QrDeposit::STATUS_PENDING) {
            return ['ok' => true, 'alreadyResolved' => true];
        }

        $res = $this->api->cancelQR($qrId);
        $code = $res['responseCode'] ?? null;

        // El banco rechaza la anulación porque el QR ya fue pagado -> acreditar.
        if ($code !== 0 && Str::contains(strtolower($res['message'] ?? ''), 'pagado')) {
            $this->applyPayment($qrId, null);

            return ['ok' => true, 'paid' => true];
        }

        QrDeposit::where('id', $deposit->id)
            ->where('status', QrDeposit::STATUS_PENDING)
            ->update(['status' => QrDeposit::STATUS_CANCELED]);

        return ['ok' => true];
    }

    /**
     * Acredita el pago en la billetera. Idempotente vía atomic claim: aunque el
     * polling y el webhook lleguen a la vez, solo el primero acredita.
     */
    public function applyPayment(string $qrId, ?array $payment = null): void
    {
        $deposit = QrDeposit::where('qr_id', $qrId)->first();
        if (! $deposit) {
            Log::warning("[baneco][apply] pago QR sin depósito asociado qrId=$qrId");

            return;
        }

        // Atomic claim: solo el primero pasa pending -> paid.
        $claimed = QrDeposit::where('id', $deposit->id)
            ->where('status', QrDeposit::STATUS_PENDING)
            ->update(['status' => QrDeposit::STATUS_PAID]);

        if ($claimed === 0) {
            Log::info("[baneco][apply] ya acreditado, se omite qrId=$qrId");

            return;
        }

        $deposit->refresh();
        $wallet = $deposit->wallet;

        DB::transaction(function () use ($deposit, $wallet, $payment, $qrId) {
            $tx = $this->wallets->deposit(
                $wallet,
                (float) $deposit->amount_usd,
                'Recarga QR Baneco',
                ['qr_id' => $qrId, 'amount_bob' => (float) $deposit->amount_bob],
            );

            $deposit->update([
                'wallet_transaction_id' => $tx->id,
                'bank_transaction_id'   => $payment['transactionId'] ?? null,
                'paid_at'               => now(),
                'meta'                  => $payment,
            ]);
        });

        Log::info("[baneco][apply] billetera acreditada +{$deposit->amount_usd} USD qrId=$qrId");
    }

    private function findUserDeposit(User $user, string $qrId): QrDeposit
    {
        $deposit = QrDeposit::where('qr_id', $qrId)->first();

        if (! $deposit || $deposit->user_id !== $user->id) {
            abort(404, 'QR no encontrado.');
        }

        return $deposit;
    }
}
