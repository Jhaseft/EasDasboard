<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BrokerAccountController;
use App\Http\Controllers\CopyTradeController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SlaveAccountController;
use App\Http\Controllers\WalletController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    $brokers = $user->brokerAccounts();
    $slaves = $user->slaveAccounts();

    return Inertia::render('Dashboard', [
        'stats' => [
            'brokers' => (clone $brokers)->count(),
            'brokers_active' => (clone $brokers)->where('is_enabled', true)->count(),
            'slaves' => (clone $slaves)->count(),
            'slaves_auto' => (clone $slaves)->where('auto_copy', true)->count(),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('bots', BotController::class)->except(['show']);
    Route::patch('/bots/{bot}/toggle', [BotController::class, 'toggle'])->name('bots.toggle');

    Route::resource('broker-accounts', BrokerAccountController::class)
        ->only(['index', 'create', 'store', 'destroy']);
    Route::patch('/broker-accounts/{brokerAccount}/toggle', [BrokerAccountController::class, 'toggle'])
        ->name('broker-accounts.toggle');
    Route::patch('/broker-accounts/{brokerAccount}/regenerate-webhook', [BrokerAccountController::class, 'regenerateWebhook'])
        ->name('broker-accounts.regenerate-webhook');
    Route::patch('/broker-accounts/{brokerAccount}/publish', [BrokerAccountController::class, 'publish'])
        ->name('broker-accounts.publish');

    // Billetera
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');

    // Módulo webhook (add-on $15/mes)
    Route::post('/billing/webhook-module', [BillingController::class, 'enableWebhookModule'])->name('billing.webhook-module.enable');
    Route::delete('/billing/webhook-module', [BillingController::class, 'disableWebhookModule'])->name('billing.webhook-module.disable');

    // Marketplace
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/{master}', [MarketplaceController::class, 'show'])->name('marketplace.show');
    Route::post('/marketplace/{master}/subscribe', [MarketplaceController::class, 'subscribe'])->name('marketplace.subscribe');
    Route::delete('/marketplace/subscriptions/{subscription}', [MarketplaceController::class, 'unsubscribe'])->name('marketplace.unsubscribe');

    Route::resource('slave-accounts', SlaveAccountController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::patch('/slave-accounts/{slaveAccount}/toggle', [SlaveAccountController::class, 'toggle'])
        ->name('slave-accounts.toggle');

    Route::get('/broker-accounts/{brokerAccount}/copy-trade', [CopyTradeController::class, 'index'])
        ->name('broker-accounts.copy-trade.index');
    Route::post('/broker-accounts/{brokerAccount}/copy-trade', [CopyTradeController::class, 'copy'])
        ->name('broker-accounts.copy-trade.copy');
    Route::post('/broker-accounts/{brokerAccount}/positions/close', [CopyTradeController::class, 'close'])
        ->name('broker-accounts.positions.close');
});

require __DIR__.'/auth.php';
