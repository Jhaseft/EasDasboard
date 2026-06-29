<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProvisionBrokerAccount;
use App\Models\BrokerAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use App\Services\Wallet\PlatformBilling;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BrokerAccountController extends Controller
{
    public function index(Request $request, PlatformBilling $billing): Response
    {
        $accounts = $request->user()->brokerAccounts()
            ->withCount(['subscriptions as followers_count' => fn ($q) => $q->where('status', 'active')])
            ->latest()
            ->get([
                'id', 'name', 'platform', 'login', 'server', 'provision_state', 'connection_status',
                'is_enabled', 'last_error', 'webhook_token', 'ingest_mode',
                'is_public', 'display_name', 'description', 'show_balance',
                'pricing_model', 'subscription_price', 'profit_share_pct',
            ]);

        return Inertia::render('BrokerAccounts/Index', [
            'accounts' => $accounts,
            'webhookModuleActive' => $billing->hasWebhookModule($request->user()),
            'webhookModuleFee' => (float) config('billing.webhook_module_fee'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('BrokerAccounts/Create', [
            'regions' => ['new-york', 'london', 'singapore'],
        ]);
    }

    public function store(Request $request, PlatformBilling $billing, WalletService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:mt4,mt5'],
            'login' => ['required', 'string', 'max:50'],
            'server' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'], // se manda a MetaApi, NO se guarda
            'region' => ['nullable', 'string', 'max:50'],
        ]);

        // Conectar una cuenta cuesta $7/mes: cobramos el primer mes al instante.
        $fee = (float) config('billing.account_fee');
        if (! $wallets->walletFor($request->user())->hasFunds($fee)) {
            return back()->withErrors([
                'balance' => "Saldo insuficiente. Conectar una cuenta cuesta \${$fee}/mes. Recarga tu billetera.",
            ]);
        }

        $account = $request->user()->brokerAccounts()->create([
            'name' => $data['name'],
            'platform' => $data['platform'],
            'login' => $data['login'],
            'server' => $data['server'],
            'region' => $data['region'] ?? config('services.metaapi.region'),
            'webhook_token' => Str::random(48),
            'provision_state' => 'validating',
        ]);

        try {
            $billing->subscribeAccount($account);
        } catch (InsufficientFundsException) {
            $account->delete();

            return back()->withErrors(['balance' => 'Saldo insuficiente para conectar la cuenta.']);
        }

        // La validacion + deploy en MetaApi puede tardar 1-2 min: a la cola.
        ProvisionBrokerAccount::dispatch($account, $data['password']);

        return redirect()
            ->route('broker-accounts.index')
            ->with('success', "Cuenta conectada (se cobró \${$fee}/mes). En validación con MetaApi; el estado se actualizará en unos minutos.");
    }

    public function toggle(Request $request, BrokerAccount $brokerAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

        $brokerAccount->update(['is_enabled' => ! $brokerAccount->is_enabled]);

        return back();
    }

    /**
     * Regenera el token del webhook (invalida la URL anterior).
     */
    public function regenerateWebhook(Request $request, BrokerAccount $brokerAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

        $brokerAccount->update(['webhook_token' => Str::random(48)]);

        return back()->with('success', 'Token del webhook regenerado. La URL anterior dejó de funcionar.');
    }

    /**
     * Publica / despublica la cuenta en el marketplace y fija su precio.
     */
    public function publish(Request $request, BrokerAccount $brokerAccount, MetaApiProvisioning $metaapi): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

        $data = $request->validate([
            'is_public'          => ['required', 'boolean'],
            'display_name'       => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'show_balance'       => ['nullable', 'boolean'],
            'pricing_model'      => ['required', 'in:subscription,profit_share'],
            'subscription_price' => ['nullable', 'numeric', 'min:0', 'max:100000', 'required_if:pricing_model,subscription'],
            'profit_share_pct'   => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:pricing_model,profit_share'],
        ]);

        $brokerAccount->update([
            'is_public'          => $data['is_public'],
            'display_name'       => $data['display_name'] ?? null,
            'description'        => $data['description'] ?? null,
            'show_balance'       => $data['show_balance'] ?? false,
            'pricing_model'      => $data['pricing_model'],
            'subscription_price' => $data['subscription_price'] ?? 0,
            'profit_share_pct'   => $data['profit_share_pct'] ?? 0,
        ]);

        // Al publicar, habilita las estadísticas auditadas (MetaStats) en MetaApi
        // para que el marketplace pueda mostrar el rendimiento. Best-effort.
        if ($data['is_public'] && $brokerAccount->metaapi_account_id) {
            try {
                $metaapi->enableMetaStats($brokerAccount->metaapi_account_id);
            } catch (\Throwable) {
                // Si falla, las stats simplemente no aparecerán hasta reintentar.
            }
        }

        return back()->with('success', $data['is_public']
            ? 'Cuenta publicada en el marketplace.'
            : 'Cuenta retirada del marketplace.');
    }

    public function destroy(Request $request, BrokerAccount $brokerAccount, MetaApiProvisioning $metaapi, PlatformBilling $billing): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

        // Al desconectar deja de cobrarse la tarifa mensual de esta cuenta.
        $billing->cancelAccount($brokerAccount);

        if ($brokerAccount->metaapi_account_id) {
            try {
                $metaapi->deleteAccount($brokerAccount->metaapi_account_id);
            } catch (\Throwable) {
                // Si MetaApi falla igual borramos el registro local; se puede limpiar luego.
            }
        }

        $brokerAccount->delete();

        return redirect()
            ->route('broker-accounts.index')
            ->with('success', 'Cuenta de broker desconectada.');
    }

    protected function authorizeAccount(Request $request, BrokerAccount $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 403);
    }
}
