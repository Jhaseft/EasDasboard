<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrDeposit extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'transaction_id',
        'qr_id',
        'qr_image',
        'amount_usd',
        'amount_bob',
        'exchange_rate',
        'currency',
        'status',
        'bank_transaction_id',
        'due_date',
        'paid_at',
        'wallet_transaction_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd'    => 'decimal:2',
            'amount_bob'    => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'due_date'      => 'date',
            'paid_at'       => 'datetime',
            'meta'          => 'array',
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
}
