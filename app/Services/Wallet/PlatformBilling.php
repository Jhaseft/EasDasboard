<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientFundsException;
use App\Models\User;

/**
 * Cobro de la plataforma al usuario (NO confundir con el marketplace, que es
 * cobro entre usuarios). Aquí cobramos el uso de la infraestructura:
 *   - $7/mes por cada cuenta de bróker conectada (maestra o esclava).
 *   - $15/mes si usa el módulo de webhooks.
 */
class PlatformBilling
{
    public function __construct(protected WalletService $wallets) {}

    /**
     * Desglose del costo mensual de plataforma de un usuario.
     *
     * @return array{accounts:int, account_fee:float, accounts_total:float, webhook:bool, webhook_fee:float, total:float}
     */
    public function monthlyBreakdown(User $user): array
    {
        $accountFee = (float) config('billing.account_fee');
        $webhookFee = (float) config('billing.webhook_module_fee');

        $brokers = $user->brokerAccounts()->where('is_enabled', true)->count();
        $slaves = $user->slaveAccounts()->where('is_enabled', true)->count();
        $accounts = $brokers + $slaves;

        $usesWebhook = $user->brokerAccounts()
            ->where('is_enabled', true)
            ->whereIn('ingest_mode', ['webhook', 'both'])
            ->exists();

        $accountsTotal = round($accounts * $accountFee, 2);
        $webhookTotal = $usesWebhook ? $webhookFee : 0.0;

        return [
            'accounts'       => $accounts,
            'account_fee'    => $accountFee,
            'accounts_total' => $accountsTotal,
            'webhook'        => $usesWebhook,
            'webhook_fee'    => $webhookFee,
            'total'          => round($accountsTotal + $webhookTotal, 2),
        ];
    }

    /**
     * Cobra el costo mensual de plataforma. Devuelve true si cobró, false si no
     * había nada que cobrar o no había saldo (en cuyo caso no descuenta nada).
     */
    public function chargeMonthly(User $user): bool
    {
        $breakdown = $this->monthlyBreakdown($user);
        if ($breakdown['total'] <= 0) {
            return false;
        }

        try {
            $this->wallets->charge(
                $this->wallets->walletFor($user),
                $breakdown['total'],
                "Plataforma: {$breakdown['accounts']} cuenta(s)"
                    .($breakdown['webhook'] ? ' + módulo webhook' : ''),
                type: 'charge',
                meta: $breakdown,
            );

            return true;
        } catch (InsufficientFundsException) {
            return false;
        }
    }
}
