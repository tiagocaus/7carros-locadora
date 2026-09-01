<?php

namespace App\Config;

/**
 * Politicas operacionais do modulo NFS-e.
 */
class NFSe
{
    /** Envio inicial somado aos reenvios automaticos/manuais regulares. */
    public const MAX_ENVIOS = 5;

    /** Excecao adicional para falhas tecnicas elegiveis ja corrigidas. */
    public const MAX_ENVIOS_EXTRAS_MANUAIS = 1;
}
