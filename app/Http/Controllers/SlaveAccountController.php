<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionSlaveAccount;
use App\Models\SlaveAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaveAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $slaves = $request->user()->slaveAccounts()
            ->with('master:id,name')
            ->latest()
            ->get(['id', 'master_account_id', 'name', 'platform', 'login', 'server', 'provision_state', 'connection_status', 'is_enabled', 'last_error', 'lot_multiplier', 'auto_copy', 'copy_mode', 'fixed_lot']);

        return Inertia::render('SlaveAccounts/Index', [
            'slaves' => $slaves,
        ]);
    }

    public function create(Request $request): Response
    {
        $masters = $request->user()->brokerAccounts()
            ->get(['id', 'name', 'platform']);

        return Inertia::render('SlaveAccounts/Create', [
            'masters' => $masters,
            'regions' => ['new-york', 'london', 'singapore'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'master_account_id' => ['required', 'integer', 'exists:broker_accounts,id'],
            'name'              => ['required', 'string', 'max:255'],
            'platform'          => ['required', 'in:mt4,mt5'],
            'login'             => ['required', 'string', 'max:50'],
            'server'            => ['required', 'string', 'max:255'],
            'password'          => ['required', 'string', 'max:255'],
            'region'            => ['nullable', 'string', 'max:50'],
            'lot_multiplier'    => ['nullable', 'numeric', 'min:0.0001', 'max:999'],
            'auto_copy'         => ['nullable', 'boolean'],
            'copy_mode'         => ['nullable', 'in:multiplier,fixed'],
            'fixed_lot'         => ['nullable', 'numeric', 'min:0.01', 'max:999'],
        ]);

        // Validar que la cuenta maestra pertenezca al usuario
        abort_unless(
            $request->user()->brokerAccounts()->where('id', $data['master_account_id'])->exists(),
            403
        );

        $slave = $request->user()->slaveAccounts()->create([
            'master_account_id' => $data['master_account_id'],
            'name'              => $data['name'],
            'platform'          => $data['platform'],
            'login'             => $data['login'],
            'server'            => $data['server'],
            'region'            => $data['region'] ?? config('services.metaapi.region'),
            'lot_multiplier'    => $data['lot_multiplier'] ?? 1.0,
            'auto_copy'         => $data['auto_copy'] ?? true,
            'copy_mode'         => $data['copy_mode'] ?? 'multiplier',
            'fixed_lot'         => $data['fixed_lot'] ?? null,
            'provision_state'   => 'validating',
        ]);

        ProvisionSlaveAccount::dispatch($slave, $data['password']);

        return redirect()
            ->route('slave-accounts.index')
            ->with('success', 'Cuenta esclava en validación con MetaApi. El estado se actualizará en unos minutos.');
    }

    public function toggle(Request $request, SlaveAccount $slaveAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $slaveAccount);

        $slaveAccount->update(['is_enabled' => ! $slaveAccount->is_enabled]);

        return back();
    }

    public function destroy(Request $request, SlaveAccount $slaveAccount, MetaApiProvisioning $metaapi): RedirectResponse
    {
        $this->authorizeAccount($request, $slaveAccount);

        if ($slaveAccount->metaapi_account_id) {
            try {
                $metaapi->deleteAccount($slaveAccount->metaapi_account_id);
            } catch (\Throwable) {
                //
            }
        }

        $slaveAccount->delete();

        return redirect()
            ->route('slave-accounts.index')
            ->with('success', 'Cuenta esclava desconectada.');
    }

    protected function authorizeAccount(Request $request, SlaveAccount $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 403);
    }
}
