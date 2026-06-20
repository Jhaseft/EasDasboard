<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionBrokerAccount;
use App\Models\BrokerAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrokerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = $request->user()->brokerAccounts()
            ->latest()
            ->get(['id', 'name', 'platform', 'login', 'server', 'provision_state', 'connection_status', 'is_enabled', 'last_error']);

        return Inertia::render('BrokerAccounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('BrokerAccounts/Create', [
            'regions' => ['new-york', 'london', 'singapore'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:mt4,mt5'],
            'login' => ['required', 'string', 'max:50'],
            'server' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'], // se manda a MetaApi, NO se guarda
            'region' => ['nullable', 'string', 'max:50'],
        ]);

        $account = $request->user()->brokerAccounts()->create([
            'name' => $data['name'],
            'platform' => $data['platform'],
            'login' => $data['login'],
            'server' => $data['server'],
            'region' => $data['region'] ?? config('services.metaapi.region'),
            'provision_state' => 'validating',
        ]);

        // La validacion + deploy en MetaApi puede tardar 1-2 min: a la cola.
        ProvisionBrokerAccount::dispatch($account, $data['password']);

        return redirect()
            ->route('broker-accounts.index')
            ->with('success', 'Cuenta en validacion con MetaApi. El estado se actualizara en unos minutos.');
    }

    public function toggle(Request $request, BrokerAccount $brokerAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

        $brokerAccount->update(['is_enabled' => ! $brokerAccount->is_enabled]);

        return back();
    }

    public function destroy(Request $request, BrokerAccount $brokerAccount, MetaApiProvisioning $metaapi): RedirectResponse
    {
        $this->authorizeAccount($request, $brokerAccount);

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
