<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use App\Services\Wallet\PlatformBilling;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected PlatformBilling $platform,
    ) {}

    public function index(Request $request): Response
    {
        $wallet = $this->wallets->walletFor($request->user());

        $transactions = $wallet->transactions()
            ->latest()
            ->take(50)
            ->get(['id', 'type', 'amount', 'balance_after', 'description', 'created_at']);

        return Inertia::render('Wallet/Index', [
            'wallet' => $wallet->only('balance', 'currency'),
            'transactions' => $transactions,
            'platformCost' => $this->platform->monthlyBreakdown($request->user()),
            'exchangeRate' => SystemConfig::usdToBob(),
            'binanceNetworks' => array_map(fn ($n) => [
                'network' => $n['network'],
                'label'   => $n['label'],
            ], (array) config('services.binance.networks', [])),
            'binanceDefaultNetwork' => config('services.binance.default_network', 'TRX'),
        ]);
    }

    /**
     * Recarga manual (placeholder). En producción esto lo hará Stripe tras un
     * pago confirmado; por ahora acredita directo para poder probar el flujo.
     */
    public function deposit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        $wallet = $this->wallets->walletFor($request->user());
        $this->wallets->deposit($wallet, (float) $data['amount'], 'Recarga manual');

        return back()->with('success', 'Billetera recargada con $'.number_format((float) $data['amount'], 2).'.');
    }
}
