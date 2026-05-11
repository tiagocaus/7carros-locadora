<?php

namespace App\Services;

/**
 * Resultado da verificação cross-tenant
 */
class CrossTenantCheckResult
{
    public function __construct(
        public bool $exists,
        public bool $isCrossTenant,
        public bool $wasLogged,
        public int $attemptCount,
        public int $suspicionScore
    ) {
    }
}
