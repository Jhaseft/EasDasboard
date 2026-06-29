<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Services\Wallet\PlatformBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(protected PlatformBilling $billing) {}

    public function enableWebhookModule(Request $request): RedirectResponse
    {
        $fee = (float) config('billing.webhook_module_fee');

        try {
            $this->billing->enableWebhookModule($request->user());
        } catch (InsufficientFundsException) {
            return back()->withErrors([
                'balance' => "Saldo insuficiente. El módulo webhook cuesta \${$fee}/mes.",
            ]);
        }

        return back()->with('success', "Módulo webhook activado (\${$fee}/mes).");
    }

    public function disableWebhookModule(Request $request): RedirectResponse
    {
        $this->billing->disableWebhookModule($request->user());

        return back()->with('success', 'Módulo webhook desactivado. No se renovará el próximo mes.');
    }
}
