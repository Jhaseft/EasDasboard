<?php

use App\Http\Controllers\BotController;
use App\Http\Controllers\BrokerAccountController;
use App\Http\Controllers\ProfileController;
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
    $bots = $request->user()->bots();

    return Inertia::render('Dashboard', [
        'stats' => [
            'total' => (clone $bots)->count(),
            'active' => (clone $bots)->where('is_active', true)->count(),
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
});

require __DIR__.'/auth.php';
