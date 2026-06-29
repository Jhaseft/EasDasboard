<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSubscription extends Model
{
    protected $fillable = [
        'subscriber_id',
        'master_account_id',
        'slave_account_id',
        'pricing_model',
        'amount',
        'profit_share_pct',
        'take_rate_pct',
        'high_water_mark',
        'status',
        'started_at',
        'next_charge_at',
        'last_charged_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'profit_share_pct' => 'decimal:2',
            'take_rate_pct'    => 'decimal:2',
            'high_water_mark'  => 'decimal:2',
            'started_at'       => 'datetime',
            'next_charge_at'   => 'datetime',
            'last_charged_at'  => 'datetime',
            'cancelled_at'     => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class, 'master_account_id');
    }

    public function slaveAccount(): BelongsTo
    {
        return $this->belongsTo(SlaveAccount::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
