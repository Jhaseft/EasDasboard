<?php

use App\Http\Controllers\Api\BotApiController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API para el runner de bots (autenticado con API key)
|--------------------------------------------------------------------------
| El runner externo (script MT5) consulta estos endpoints enviando la
| cabecera "X-API-Key: <BOT_API_KEY>" para obtener cómo abrir operaciones.
*/

Route::middleware('api.key')->group(function () {
    Route::get('/bots/active', [BotApiController::class, 'active'])->name('api.bots.active');
    Route::get('/bots/signal', [BotApiController::class, 'signal'])->name('api.bots.signal');
    Route::get('/bots/{bot}/signal', [BotApiController::class, 'signalById'])->name('api.bots.signal.id');
    Route::get('/bots/{bot}', [BotApiController::class, 'show'])->name('api.bots.show');

    // Worker de Python (MetaApi cloud): cuentas operables + bots activos.
    Route::get('/worker/accounts', [WorkerController::class, 'accounts'])->name('api.worker.accounts');
});
