<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientFundsException;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cobro de la PLATAFORMA al usuario (no confundir con el marketplace, que es
 * entre usuarios). Modelo "Opción B": se cobra al conectar y se renueva cada mes.
 *   - $7/mes por cada cuenta conectada (maestra o esclava).
 *   - $15/mes módulo webhook (add-on por usuario).
 */
class PlatformBilling
{
    public function __construct(protected WalletService $wallets) {}

    /**
     * Suscribe una cuenta recién conectada y cobra su primer mes de inmediato.
     * Lanza InsufficientFundsException si no hay saldo (el llamador debe abortar
     * la conexión de la cuenta).
     */
    public function subscribeAccount(Model $account): PlatformSubscription
    {
        $amount = (float) config('billing.account_fee');
        $name = $account->name ?? 'cuenta';

        return DB::transaction(function () use ($account, $amount, $name) {
            $this->wallets->charge(
                $this->wallets->walletFor($account->user),
                $amount,
                "Cuenta conectada: {$name}",
            );

            return PlatformSubscription::create([
                'user_id'         => $account->user_id,
                'billable_type'   => $account->getMorphClass(),
                'billable_id'     => $account->getKey(),
                'item'            => 'account',
                'amount'          => $amount,
                'status'          => 'active',
                'started_at'      => now(),
                'last_charged_at' => now(),
                'next_charge_at'  => Carbon::now()->addMonth(),
            ]);
        });
    }

    /**
     * Cancela la suscripción de una cuenta (al desconectarla deja de cobrarse).
     */
    public function cancelAccount(Model $account): void
    {
        PlatformSubscription::where('billable_type', $account->getMorphClass())
            ->where('billable_id', $account->getKey())
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function hasWebhookModule(User $user): bool
    {
        return PlatformSubscription::where('user_id', $user->id)
            ->where('item', 'webhook_module')
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Activa el módulo webhook y cobra su primer mes. Idempotente.
     */
    public function enableWebhookModule(User $user): PlatformSubscription
    {
        $existing = PlatformSubscription::where('user_id', $user->id)
            ->where('item', 'webhook_module')
            ->where('status', 'active')
            ->first();
        if ($existing) {
            return $existing;
        }

        $amount = (float) config('billing.webhook_module_fee');

        return DB::transaction(function () use ($user, $amount) {
            $this->wallets->charge(
                $this->wallets->walletFor($user),
                $amount,
                'Módulo webhook',
            );

            return PlatformSubscription::create([
                'user_id'         => $user->id,
                'item'            => 'webhook_module',
                'amount'          => $amount,
                'status'          => 'active',
                'started_at'      => now(),
                'last_charged_at' => now(),
                'next_charge_at'  => Carbon::now()->addMonth(),
            ]);
        });
    }

    public function disableWebhookModule(User $user): void
    {
        PlatformSubscription::where('user_id', $user->id)
            ->where('item', 'webhook_module')
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /**
     * Renueva una suscripción cuyo periodo venció. Devuelve true si cobró.
     */
    public function renew(PlatformSubscription $sub): bool
    {
        try {
            $this->wallets->charge(
                $this->wallets->walletFor($sub->user),
                (float) $sub->amount,
                $sub->item === 'webhook_module' ? 'Renovación módulo webhook' : 'Renovación cuenta conectada',
                $sub,
            );
            $sub->forceFill([
                'status'          => 'active',
                'last_charged_at' => now(),
                'next_charge_at'  => Carbon::now()->addMonth(),
            ])->save();

            return true;
        } catch (InsufficientFundsException) {
            $sub->update(['status' => 'past_due']);

            return false;
        }
    }

    /**
     * Desglose del costo mensual actual del usuario (lo que se le está cobrando).
     *
     * @return array{accounts:int, account_fee:float, accounts_total:float, webhook:bool, webhook_fee:float, total:float}
     */
    public function monthlyBreakdown(User $user): array
    {
        $accountFee = (float) config('billing.account_fee');
        $webhookFee = (float) config('billing.webhook_module_fee');

        $accounts = PlatformSubscription::where('user_id', $user->id)
            ->where('item', 'account')
            ->where('status', 'active')
            ->count();

        $webhook = $this->hasWebhookModule($user);

        $accountsTotal = round($accounts * $accountFee, 2);

        return [
            'accounts'       => $accounts,
            'account_fee'    => $accountFee,
            'accounts_total' => $accountsTotal,
            'webhook'        => $webhook,
            'webhook_fee'    => $webhookFee,
            'total'          => round($accountsTotal + ($webhook ? $webhookFee : 0), 2),
        ];
    }
}
