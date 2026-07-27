<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Indica que um ou mais bloqueios nao tiveram a liberacao confirmada.
 */
class AuthorizationHoldReleaseException extends RuntimeException
{
    public function __construct(private readonly array $result)
    {
        parent::__construct('Nao foi possivel confirmar a liberacao de todos os bloqueios ativos.');
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
