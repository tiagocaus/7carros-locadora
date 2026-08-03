<?php

namespace App\Exceptions;

/**
 * Indica que uma notificacao automatica nao possui destinatario utilizavel.
 *
 * Ausencia ou formato invalido de email/telefone e uma condicao operacional
 * esperada. O chamador pode ignorar o canal sem registrar erro no servidor.
 */
class NotificationRecipientUnavailableException extends \InvalidArgumentException
{
}
