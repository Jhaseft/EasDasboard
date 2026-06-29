<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signal extends Model
{
    protected $fillable = [
        'broker_account_id',
        'source',
        'external_id',
        'action',
        'symbol',
        'volume',
        'sl',
        'tp',
        'status',
        'error',
        'payload',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'volume'      => 'decimal:2',
            'sl'          => 'decimal:5',
            'tp'          => 'decimal:5',
            'payload'     => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class);
    }
}
