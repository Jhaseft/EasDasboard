<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración global de la plataforma. Tabla de FILA ÚNICA, sin id: cada columna
 * es un ajuste editable por el administrador sin tocar el .env. Se irán agregando
 * columnas según se necesiten.
 *
 * Como no hay clave primaria, las escrituras se hacen con el query builder
 * (afectan la única fila), no con save() sobre la instancia.
 */
class SystemConfig extends Model
{
    protected $table = 'system_config';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'usd_to_bob' => 'decimal:4',
        ];
    }

    /**
     * La fila única de configuración (la crea con los valores del .env si falta).
     */
    public static function row(): self
    {
        if (! static::query()->exists()) {
            static::query()->insert([
                'usd_to_bob' => (float) config('services.baneco.usd_to_bob', 6.9),
            ]);
        }

        return static::query()->first();
    }

    /**
     * Tipo de cambio USD→BOB vigente. Se lee directo de la BD (sin caché) para
     * que los cambios hechos por el administrador apliquen al instante, aunque
     * los edite a mano en la tabla. Es una fila diminuta: el costo es despreciable.
     */
    public static function usdToBob(): float
    {
        $rate = (float) static::row()->usd_to_bob;

        return $rate > 0 ? $rate : 6.9;
    }

    /**
     * Actualiza ajustes de la fila única.
     */
    public static function put(array $values): void
    {
        if (static::query()->exists()) {
            static::query()->update($values);
        } else {
            static::query()->insert($values);
        }
    }
}
