<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProvisionSlaveAccount;
use App\Models\SlaveAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use App\Services\Wallet\PlatformBilling;
use App\Services\Wallet\WalletService;
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

    public function store(Request $request, PlatformBilling $billing, WalletService $wallets): RedirectResponse
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

        // Una esclava también es una "cuenta conectada": $7/mes.
        $fee = (float) config('billing.account_fee');
        if (! $wallets->walletFor($request->user())->hasFunds($fee)) {
            return back()->withErrors([
                'balance' => "Saldo insuficiente. Conectar una cuenta cuesta \${$fee}/mes. Recarga tu billetera.",
            ]);
        }

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

        try {
            $billing->subscribeAccount($slave);
        } catch (InsufficientFundsException) {
            $slave->delete();

            return back()->withErrors(['balance' => 'Saldo insuficiente para conectar la cuenta.']);
        }

        ProvisionSlaveAccount::dispatch($slave, $data['password']);

        return redirect()
            ->route('slave-accounts.index')
            ->with('success', "Cuenta esclava conectada (se cobró \${$fee}/mes). En validación con MetaApi.");
    }

    public function edit(Request $request, SlaveAccount $slaveAccount): Response
    {
        $this->authorizeAccount($request, $slaveAccount);

        $slaveAccount->load('master:id,name');

        return Inertia::render('SlaveAccounts/Edit', [
            'slave' => $slaveAccount->only(
                'id', 'name', 'lot_multiplier', 'auto_copy', 'copy_mode', 'fixed_lot',
                'platform', 'login', 'server', 'master_account_id',
            ) + ['master' => $slaveAccount->master?->only('id', 'name')],
        ]);
    }

    public function update(Request $request, SlaveAccount $slaveAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $slaveAccount);

        // Solo editamos los ajustes de copia. Cambiar login/servidor/contraseña
        // exige re-provisionar en MetaApi, así que eso se hace borrando y
        // reconectando la cuenta, no aquí.
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'lot_multiplier' => ['nullable', 'numeric', 'min:0.0001', 'max:999'],
            'auto_copy'      => ['nullable', 'boolean'],
            'copy_mode'      => ['nullable', 'in:multiplier,fixed'],
            'fixed_lot'      => ['nullable', 'numeric', 'min:0.01', 'max:999', 'required_if:copy_mode,fixed'],
        ]);

        $slaveAccount->update([
            'name'           => $data['name'],
            'lot_multiplier' => $data['lot_multiplier'] ?? $slaveAccount->lot_multiplier,
            'auto_copy'      => $data['auto_copy'] ?? false,
            'copy_mode'      => $data['copy_mode'] ?? 'multiplier',
            'fixed_lot'      => $data['fixed_lot'] ?? null,
        ]);

        return redirect()
            ->route('slave-accounts.index')
            ->with('success', 'Cuenta esclava actualizada.');
    }

    public function toggle(Request $request, SlaveAccount $slaveAccount): RedirectResponse
    {
        $this->authorizeAccount($request, $slaveAccount);

        $slaveAccount->update(['is_enabled' => ! $slaveAccount->is_enabled]);

        return back();
    }

    public function destroy(Request $request, SlaveAccount $slaveAccount, MetaApiProvisioning $metaapi, PlatformBilling $billing): RedirectResponse
    {
        $this->authorizeAccount($request, $slaveAccount);

        // Al desconectar deja de cobrarse la tarifa mensual de esta cuenta.
        $billing->cancelAccount($slaveAccount);

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
