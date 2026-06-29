<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte de un intento de operacion enviado por el worker de Python.
 * status:
 *   - opened          -> la operacion se abrio correctamente.
 *   - rejected_limits -> no se abrio porque el SL/TP excede los limites del broker.
 *   - failed          -> no se abrio por otro error de MetaApi.
 */
class BotTrade extends Model
{
    protected $fillable = [
        'bot_id',
        'broker_account_id',
        'symbol',
        'direction',
        'status',
        'position_id',
        'requested_sl',
        'requested_tp',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'requested_sl' => 'decimal:5',
            'requested_tp' => 'decimal:5',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class);
    }
}
