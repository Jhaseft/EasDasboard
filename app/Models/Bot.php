<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bot extends Model
{
    /** @use HasFactory<\Database\Factories\BotFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'broker_account_id',
        'name',
        'is_active',
        'symbols',
        'timeframe',
        'strategy',
        'parameters',
        'direction',
        'lot_size',
        'stop_loss_pips',
        'take_profit_pips',
        'max_open_trades',
        'risk_percent',
        'trailing_stop_pips',
        'trading_start_time',
        'trading_end_time',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'symbols' => 'array',
            'parameters' => 'array',
            'lot_size' => 'decimal:2',
            'risk_percent' => 'decimal:2',
        ];
    }

    /**
     * Parametros por defecto de cada estrategia. El admin los edita desde el
     * panel; cualquier clave ausente se rellena con estos valores.
     *
     * @return array<string, mixed>
     */
    public static function defaultParameters(string $strategy): array
    {
        return match ($strategy) {
            'asian_breakout' => [
                // Gestion prop firm
                'max_daily_loss_pct' => 4.0,
                'risk_per_trade_pct' => 0.5,
                'max_daily_trades' => 3,
                'max_lot_cap' => 0.0,
                // Filtro de senal
                'volume_surge_multiplier' => 2.0,
                // Ciclo de intento / forzado
                'force_trade_cycle' => true,
                'attempt_interval_min' => 5,
                'auto_relax_filters' => true,
                'relax_per_attempt' => 0.15,
                'max_relax_steps' => 6,
                'force_entry_at_max' => true,
                'trade_sessions_only' => true,
                // Sesiones (hora del servidor del broker)
                'asian_start_hour' => 0,
                'asian_end_hour' => 7,
                'london_start_hour' => 8,
                'london_end_hour' => 10,
                'ny_start_hour' => 15,
                'ny_end_hour' => 17,
                // Gestion R:R y tecnicos
                'tp_rr_multiplier' => 2.0,
                'atr_period' => 14,
                'atr_sl_floor_mult' => 1.0,
                'volume_lookback' => 20,
                'max_spread_points' => 60,
                'breakout_buffer_pips' => 2.0,
                'min_free_margin_pct' => 20.0,
                'remove_ea_on_dd' => true,
            ],
            default => [],
        };
    }

    /**
     * Parametros del bot fusionados con los valores por defecto de su estrategia.
     *
     * @return array<string, mixed>
     */
    public function mergedParameters(): array
    {
        return array_merge(
            self::defaultParameters($this->strategy ?? 'simple'),
            is_array($this->parameters) ? $this->parameters : []
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class);
    }

    /**
     * Whether the current server time is inside the configured trading window.
     * If no window is set, the bot is allowed to trade at any time.
     */
    public function withinTradingWindow(?\DateTimeInterface $now = null): bool
    {
        if (empty($this->trading_start_time) || empty($this->trading_end_time)) {
            return true;
        }

        $now = $now ?? now();
        $current = $now->format('H:i');
        $start = substr((string) $this->trading_start_time, 0, 5);
        $end = substr((string) $this->trading_end_time, 0, 5);

        // Same-day window (e.g. 08:00 -> 20:00).
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        // Overnight window (e.g. 22:00 -> 06:00).
        return $current >= $start || $current <= $end;
    }

    /**
     * Normalized instructions the external runner uses to open an operation.
     *
     * @return array<string, mixed>
     */
    public function toOperationConfig(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbols' => $this->symbols ?? [],
            'timeframe' => $this->timeframe,
            'entry' => [
                'direction' => $this->direction,
                'lot_size' => (float) $this->lot_size,
                'stop_loss_pips' => $this->stop_loss_pips,
                'take_profit_pips' => $this->take_profit_pips,
                'max_open_trades' => $this->max_open_trades,
            ],
            'risk' => [
                'risk_percent' => $this->risk_percent !== null ? (float) $this->risk_percent : null,
                'trailing_stop_pips' => $this->trailing_stop_pips,
                'trading_start_time' => $this->trading_start_time ? substr((string) $this->trading_start_time, 0, 5) : null,
                'trading_end_time' => $this->trading_end_time ? substr((string) $this->trading_end_time, 0, 5) : null,
            ],
            'within_trading_window' => $this->withinTradingWindow(),
        ];
    }
}
