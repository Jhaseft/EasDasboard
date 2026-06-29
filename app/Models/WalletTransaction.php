<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta'          => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
