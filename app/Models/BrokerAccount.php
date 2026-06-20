<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrokerAccount extends Model
{
    /** @use HasFactory<\Database\Factories\BrokerAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
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
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Bot, $this>
     */
    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    /**
     * Lista para que el worker pueda operar: provisionada y habilitada.
     */
    public function isOperable(): bool
    {
        return $this->is_enabled
            && $this->provision_state === 'deployed'
            && ! empty($this->metaapi_account_id);
    }
}
