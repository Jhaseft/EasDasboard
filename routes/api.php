<?php

use App\Http\Controllers\Api\BotApiController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API para el runner de bots (autenticado con API key)
|--------------------------------------------------------------------------
| El runner externo (script MT5) consulta estos endpoints enviando la
| cabecera "X-API-Key: <BOT_API_KEY>" para obtener cómo abrir operaciones.
*/

/*
| Ingesta por webhook (TradingView / MT5 / Python). El token en la URL identifica
| y autentica la cuenta. Con throttle para evitar abuso.
*/
Route::post('/webhook/{token}', [WebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('api.webhook.receive');

Route::middleware('api.key')->group(function () {
    Route::get('/bots/active', [BotApiController::class, 'active'])->name('api.bots.active');
    Route::get('/bots/signal', [BotApiController::class, 'signal'])->name('api.bots.signal');
    Route::get('/bots/{bot}/signal', [BotApiController::class, 'signalById'])->name('api.bots.signal.id');
    Route::get('/bots/{bot}', [BotApiController::class, 'show'])->name('api.bots.show');

    // Worker de Python (MetaApi cloud): cuentas operables + bots activos.
    Route::get('/worker/accounts', [WorkerController::class, 'accounts'])->name('api.worker.accounts');

    // Copy-trading automático: el worker lee las maestras/esclavas y reporta copias.
    Route::get('/worker/copy-accounts', [WorkerController::class, 'copyAccounts'])->name('api.worker.copy-accounts');
    Route::post('/worker/copy-trades', [WorkerController::class, 'reportCopyTrades'])->name('api.worker.copy-trades');
});
