<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinanceDepositIntent extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_REJECTED  = 'rejected';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'network',
        'coin',
        'wallet_address',
        'amount_usd',
        'expected_usdt',
        'status',
        'txid',
        'wallet_transaction_id',
        'binance_data',
        'failure_reason',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd'    => 'decimal:2',
            'expected_usdt' => 'decimal:8',
            'binance_data'  => 'array',
            'expires_at'    => 'datetime',
            'confirmed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || $this->expires_at->isPast();
    }

    /**
     * Intents pendientes que aún no vencen (los que el matcher debe considerar).
     */
    public function scopePendingActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now());
    }
}
