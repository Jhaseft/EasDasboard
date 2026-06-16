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
        'name',
        'is_active',
        'symbols',
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
            'lot_size' => 'decimal:2',
            'risk_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
