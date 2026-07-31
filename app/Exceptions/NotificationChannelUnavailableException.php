<?php

namespace App\Exceptions;

/**
 * Indica que uma notificacao foi ignorada por configuracao operacional.
 *
 * Nao representa falha de banco, fila ou provedor e pode ser tratada pelos
 * fluxos chamadores sem registrar erro no log do servidor.
 */
class NotificationChannelUnavailableException extends \InvalidArgumentException
{
}
