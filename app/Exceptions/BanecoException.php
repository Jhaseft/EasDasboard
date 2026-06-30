<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error al comunicarse con la API de Banco Económico (QR Simple). El mensaje es
 * apto para mostrar al usuario; el detalle técnico va al log.
 */
class BanecoException extends RuntimeException
{
}
