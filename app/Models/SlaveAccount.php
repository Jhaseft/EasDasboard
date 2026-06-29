<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaveAccount extends Model
{
    protected $fillable = [
        'user_id',
        'master_account_id',
        'name',
        'platform',
        'login',
        'server',
        'metaapi_account_id',
        'region',
        'provision_state',
        'connection_status',
        'last_error',
        'is_enabled',
        'lot_multiplier',
        'auto_copy',
        'copy_mode',
        'fixed_lot',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'      => 'boolean',
            'auto_copy'       => 'boolean',
            'lot_multiplier'  => 'decimal:4',
            'fixed_lot'       => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(BrokerAccount::class, 'master_account_id');
    }

    public function copiedTrades(): HasMany
    {
        return $this->hasMany(CopiedTrade::class);
    }

    public function isOperable(): bool
    {
        return $this->is_enabled
            && $this->provision_state === 'deployed'
            && ! empty($this->metaapi_account_id);
    }
}
