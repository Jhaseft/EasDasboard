<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionSlaveAccount;
use App\Models\BrokerAccount;
use App\Models\MarketplaceSubscription;
use App\Services\Wallet\MarketplaceBilling;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected MarketplaceBilling $billing,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $masters = BrokerAccount::public()
            ->withCount(['subscriptions as followers_count' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->map(fn (BrokerAccount $m) => [
                'id'                 => $m->id,
                'name'               => $m->publicName(),
                'description'        => $m->description,
                'platform'           => $m->platform,
                'pricing_model'      => $m->pricing_model,
                'subscription_price' => (float) $m->subscription_price,
                'profit_share_pct'   => (float) $m->profit_share_pct,
                'followers_count'    => $m->followers_count,
                'is_own'             => $m->user_id === $userId,
            ]);

        $mySubs = MarketplaceSubscription::where('subscriber_id', $userId)
            ->with('master:id,name,display_name')
            ->get()
            ->map(fn (MarketplaceSubscription $s) => [
                'id'             => $s->id,
                'master'         => $s->master?->publicName(),
                'master_id'      => $s->master_account_id,
                'pricing_model'  => $s->pricing_model,
                'amount'         => (float) $s->amount,
                'status'         => $s->status,
                'next_charge_at' => $s->next_charge_at,
            ]);

        return Inertia::render('Marketplace/Index', [
            'masters'  => $masters,
            'mySubs'   => $mySubs,
            'balance'  => (float) $this->wallets->walletFor($request->user())->balance,
        ]);
    }

    public function show(Request $request, BrokerAccount $master): Response
    {
        abort_unless($master->is_public, 404);

        $master->loadCount(['subscriptions as followers_count' => fn ($q) => $q->where('status', 'active')]);

        $subscribed = MarketplaceSubscription::where('subscriber_id', $request->user()->id)
            ->where('master_account_id', $master->id)
            ->where('status', 'active')
            ->exists();

        return Inertia::render('Marketplace/Show', [
            'master' => [
                'id'                 => $master->id,
                'name'               => $master->publicName(),
                'description'        => $master->description,
                'platform'           => $master->platform,
                'pricing_model'      => $master->pricing_model,
                'subscription_price' => (float) $master->subscription_price,
                'profit_share_pct'   => (float) $master->profit_share_pct,
                'followers_count'    => $master->followers_count,
                'is_own'             => $master->user_id === $request->user()->id,
            ],
            'subscribed' => $subscribed,
            'balance'    => (float) $this->wallets->walletFor($request->user())->balance,
            'regions'    => ['new-york', 'london', 'singapore'],
        ]);
    }

    /**
     * El seguidor se suscribe a una maestra pública: crea su cuenta esclava que
     * copiará a esa maestra y se cobra el primer periodo de su billetera.
     */
    public function subscribe(Request $request, BrokerAccount $master): RedirectResponse
    {
        abort_unless($master->is_public && $master->is_enabled, 404);
        abort_if($master->user_id === $request->user()->id, 403, 'No puedes seguir tu propia cuenta.');

        $user = $request->user();

        if (MarketplaceSubscription::where('subscriber_id', $user->id)
            ->where('master_account_id', $master->id)
            ->where('status', 'active')
            ->exists()) {
            return back()->with('success', 'Ya sigues esta cuenta.');
        }

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'platform'       => ['required', 'in:mt4,mt5'],
            'login'          => ['required', 'string', 'max:50'],
            'server'         => ['required', 'string', 'max:255'],
            'password'       => ['required', 'string', 'max:255'],
            'region'         => ['nullable', 'string', 'max:50'],
            'lot_multiplier' => ['nullable', 'numeric', 'min:0.0001', 'max:999'],
            'copy_mode'      => ['nullable', 'in:multiplier,fixed'],
            'fixed_lot'      => ['nullable', 'numeric', 'min:0.01', 'max:999', 'required_if:copy_mode,fixed'],
        ]);

        // Cobro por adelantado del primer periodo: validar saldo antes de crear nada.
        $amount = $master->pricing_model === 'subscription' ? (float) $master->subscription_price : 0.0;
        $wallet = $this->wallets->walletFor($user);
        if ($amount > 0 && ! $wallet->hasFunds($amount)) {
            return back()->withErrors([
                'amount' => 'Saldo insuficiente. Recarga tu billetera (necesitas $'.number_format($amount, 2).').',
            ]);
        }

        $slave = $user->slaveAccounts()->create([
            'master_account_id' => $master->id,
            'name'              => $data['name'],
            'platform'          => $data['platform'],
            'login'             => $data['login'],
            'server'            => $data['server'],
            'region'            => $data['region'] ?? config('services.metaapi.region'),
            'lot_multiplier'    => $data['lot_multiplier'] ?? 1.0,
            'auto_copy'         => true,
            'copy_mode'         => $data['copy_mode'] ?? 'multiplier',
            'fixed_lot'         => $data['fixed_lot'] ?? null,
            'provision_state'   => 'validating',
        ]);

        $sub = MarketplaceSubscription::create([
            'subscriber_id'     => $user->id,
            'master_account_id' => $master->id,
            'slave_account_id'  => $slave->id,
            'pricing_model'     => $master->pricing_model,
            'amount'            => $amount,
            'profit_share_pct'  => (float) $master->profit_share_pct,
            'take_rate_pct'     => (float) config('marketplace.take_rate_pct'),
            'status'            => 'active',
            'started_at'        => now(),
        ]);

        $this->billing->chargeSubscription($sub);

        ProvisionSlaveAccount::dispatch($slave, $data['password']);

        return redirect()
            ->route('marketplace.index')
            ->with('success', 'Te suscribiste a '.$master->publicName().'. Tu cuenta se está conectando.');
    }

    public function unsubscribe(Request $request, MarketplaceSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->subscriber_id === $request->user()->id, 403);

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        // Pausa la copia de la esclava asociada (deja de replicar).
        $subscription->slaveAccount?->update(['auto_copy' => false]);

        return back()->with('success', 'Suscripción cancelada. Ya no se copiarán nuevas operaciones.');
    }
}
