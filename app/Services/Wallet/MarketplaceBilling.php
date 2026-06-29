<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientFundsException;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cobra las suscripciones del marketplace: descuenta de la billetera del seguidor
 * y reparte el dinero entre el proveedor (cuenta maestra) y el SaaS (take rate).
 *
 * Solo implementa el modelo 'subscription' (cuota mensual fija). El modelo
 * 'profit_share' requiere el P&L realizado de la cuenta (lo traerá una fase
 * posterior con el historial de MetaApi) — aquí se omite explícitamente.
 */
class MarketplaceBilling
{
    public function __construct(protected WalletService $wallets) {}

    /**
     * Cobra un periodo de la suscripción. Devuelve true si cobró, false si no
     * (saldo insuficiente -> queda en past_due, o modelo no soportado aún).
     */
    public function chargeSubscription(MarketplaceSubscription $sub): bool
    {
        if ($sub->pricing_model !== 'subscription') {
            return false; // profit_share: pendiente de fase con P&L real
        }

        $amount = (float) $sub->amount;
        if ($amount <= 0) {
            $this->advance($sub);

            return true; // gratis: solo avanza el periodo
        }

        $subscriber = $sub->subscriber;
        $provider = $sub->master?->user;
        if (! $subscriber || ! $provider) {
            return false;
        }

        $takeRate = (float) $sub->take_rate_pct;
        $fee = round($amount * $takeRate / 100, 2);
        $providerAmount = round($amount - $fee, 2);

        try {
            DB::transaction(function () use ($sub, $subscriber, $provider, $amount, $fee, $providerAmount) {
                // 1) Cobra al seguidor.
                $this->wallets->charge(
                    $this->wallets->walletFor($subscriber),
                    $amount,
                    "Suscripción a {$sub->master->publicName()}",
                    $sub,
                );

                // 2) Paga al proveedor su parte.
                $this->wallets->credit(
                    $this->wallets->walletFor($provider),
                    $providerAmount,
                    "Ingreso por seguidor ({$subscriber->name})",
                    $sub,
                    'payout',
                );

                // 3) Comisión del SaaS (si hay cuenta de plataforma configurada).
                if ($fee > 0 && ($platformId = config('marketplace.platform_user_id'))) {
                    if ($platform = User::find($platformId)) {
                        $this->wallets->credit(
                            $this->wallets->walletFor($platform),
                            $fee,
                            "Comisión marketplace (sub #{$sub->id})",
                            $sub,
                            'fee',
                        );
                    }
                }

                $this->advance($sub, markCharged: true);
            });

            return true;
        } catch (InsufficientFundsException) {
            $sub->update(['status' => 'past_due']);

            return false;
        }
    }

    protected function advance(MarketplaceSubscription $sub, bool $markCharged = false): void
    {
        $sub->forceFill([
            'status'         => 'active',
            'last_charged_at' => $markCharged ? now() : $sub->last_charged_at,
            'next_charge_at' => Carbon::now()->addMonth(),
        ])->save();
    }
}
