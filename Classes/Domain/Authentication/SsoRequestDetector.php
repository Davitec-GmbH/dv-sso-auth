<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Domain\Authentication;

final class SsoRequestDetector
{
    /**
     * @param array<string, mixed> $server
     */
    public function isSsoRequest(array $server): bool
    {
        $authType = strtolower((string)($server['AUTH_TYPE'] ?? ''));

        if ($authType === 'shibboleth') {
            return true;
        }

        return isset($server['Shib_Session_ID']) || isset($server['REDIRECT_Shib_Session_ID']);
    }
}
