<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotApiController extends Controller
{
    /**
     * Return every active bot with the instructions to open operations.
     * Optionally filter by ?symbol=EURUSD to get only the bots that trade it.
     */
    public function active(Request $request): JsonResponse
    {
        $symbol = $request->query('symbol');
        $symbol = is_string($symbol) ? strtoupper(trim($symbol)) : null;

        $bots = Bot::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (Bot $bot) use ($symbol) {
                if ($symbol === null) {
                    return true;
                }

                return in_array($symbol, $bot->symbols ?? [], true);
            })
            ->map(fn (Bot $bot) => $bot->toOperationConfig())
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'count' => $bots->count(),
            'bots' => $bots,
        ]);
    }

    /**
     * Señal plana para un EA asociado a un par. El servidor decide si el EA
     * debe operar (should_trade) según el estado del bot en la base de datos.
     * El EA solo ejecuta lo que aquí se le indica.
     *
     * GET /api/bots/signal?symbol=EURUSD
     */
    public function signal(Request $request): JsonResponse
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', '')));

        if ($symbol === '') {
            return response()->json(['found' => false, 'should_trade' => false, 'reason' => 'symbol_required'], 422);
        }

        $bot = Bot::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (Bot $bot) => in_array($symbol, $bot->symbols ?? [], true));

        if (! $bot) {
            return response()->json([
                'server_time' => now()->toIso8601String(),
                'symbol' => $symbol,
                'found' => false,
                'should_trade' => false,
                'reason' => 'no_active_bot',
            ]);
        }

        $withinWindow = $bot->withinTradingWindow();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'symbol' => $symbol,
            'found' => true,
            'bot_id' => $bot->id,
            'name' => $bot->name,
            // El servidor decide: solo operar si está activo y dentro del horario.
            'should_trade' => $withinWindow,
            'reason' => $withinWindow ? 'ok' : 'outside_trading_window',
            'direction' => $bot->direction,
            'lot_size' => (float) $bot->lot_size,
            'stop_loss_pips' => $bot->stop_loss_pips ?? 0,
            'take_profit_pips' => $bot->take_profit_pips ?? 0,
            'max_open_trades' => $bot->max_open_trades,
            'trailing_stop_pips' => $bot->trailing_stop_pips ?? 0,
        ]);
    }

    /**
     * Return the operation config for a single active bot.
     */
    public function show(Bot $bot): JsonResponse
    {
        abort_unless($bot->is_active, 404, 'Bot is not active.');

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'bot' => $bot->toOperationConfig(),
        ]);
    }
}
