<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error al comunicarse con la API de Binance (historial de depósitos). El mensaje
 * es apto para mostrar al usuario; el detalle técnico va al log.
 */
class BinanceException extends RuntimeException
{
}
