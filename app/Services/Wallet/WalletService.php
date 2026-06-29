<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientFundsException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maneja la billetera interna con contabilidad de doble entrada: cada cambio de
 * saldo deja un WalletTransaction. Todas las operaciones bloquean la fila de la
 * billetera (lockForUpdate) dentro de una transacción para evitar carreras.
 */
class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Ingreso de fondos (recarga). amount > 0.
     */
    public function deposit(Wallet $wallet, float $amount, string $description = 'Recarga', array $meta = []): WalletTransaction
    {
        return $this->apply($wallet, 'deposit', abs($amount), $description, null, $meta);
    }

    /**
     * Cobro (sale dinero). Lanza InsufficientFundsException si no alcanza.
     */
    public function charge(Wallet $wallet, float $amount, string $description, ?Model $reference = null, string $type = 'charge', array $meta = []): WalletTransaction
    {
        return $this->apply($wallet, $type, -abs($amount), $description, $reference, $meta);
    }

    /**
     * Acreditación (entra dinero, ej. pago al proveedor). amount > 0.
     */
    public function credit(Wallet $wallet, float $amount, string $description, ?Model $reference = null, string $type = 'payout', array $meta = []): WalletTransaction
    {
        return $this->apply($wallet, $type, abs($amount), $description, $reference, $meta);
    }

    /**
     * Aplica un movimiento con signo y registra la transacción de forma atómica.
     */
    protected function apply(Wallet $wallet, string $type, float $signedAmount, string $description, ?Model $reference, array $meta): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $type, $signedAmount, $description, $reference, $meta) {
            // Re-lee con bloqueo para que el saldo sea consistente bajo concurrencia.
            $locked = Wallet::whereKey($wallet->getKey())->lockForUpdate()->first();

            $newBalance = round((float) $locked->balance + $signedAmount, 2);

            if ($newBalance < 0) {
                throw new InsufficientFundsException();
            }

            $locked->update(['balance' => $newBalance]);

            $tx = new WalletTransaction([
                'type'          => $type,
                'amount'        => $signedAmount,
                'balance_after' => $newBalance,
                'description'   => $description,
                'meta'          => $meta ?: null,
            ]);
            $tx->wallet()->associate($locked);
            if ($reference) {
                $tx->reference()->associate($reference);
            }
            $tx->save();

            // Mantén el modelo recibido en sintonía con el saldo nuevo.
            $wallet->setAttribute('balance', $newBalance);

            return $tx;
        });
    }
}
