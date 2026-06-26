<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopiedTrade extends Model
{
    protected $fillable = [
        'master_account_id',
        'slave_account_id',
        'master_position_id',
        'slave_position_id',
        'symbol',
        'direction',
        'master_lot',
        'slave_lot',
        'status',
        'error',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'master_lot' => 'decimal:2',
            'slave_lot'  => 'decimal:2',
            'opened_at'  => 'datetime',
            'closed_at'  => 'datetime',
        ];
    }

    // un copied trade pertenece a un solo master account
    public function masterAccount(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class, 'master_account_id');
    }

    public function slaveAccount(): BelongsTo
    {
        return $this->belongsTo(SlaveAccount::class, 'slave_account_id');
    }
}
