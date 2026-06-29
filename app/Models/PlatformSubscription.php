<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlatformSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'billable_type',
        'billable_id',
        'item',
        'amount',
        'status',
        'started_at',
        'next_charge_at',
        'last_charged_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'started_at'      => 'datetime',
            'next_charge_at'  => 'datetime',
            'last_charged_at' => 'datetime',
            'cancelled_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
