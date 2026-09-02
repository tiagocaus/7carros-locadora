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

    /**
     * Codigos cIndOp aceitos pelo layout NT004 atualmente ativo nos
     * ambientes Nacional/Betha. A NT009 ainda nao esta ativa nesses fluxos.
     */
    public const CINDOP_NT004 = [
        '020101', '020201', '020301',
        '030101', '030102', '030103', '030104',
        '040101',
        '050101', '050102', '050103', '050104', '050201',
        '060101',
        '070101', '070102',
        '080101',
        '100101', '100102', '100201',
        '100301', '100302', '100401',
        '100501', '100502', '100601',
    ];

    public static function cIndOpNT004Valido(string $codigo): bool
    {
        return in_array($codigo, self::CINDOP_NT004, true);
    }
}
