<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Hook;

final class UserAuthentication
{
    public function backendLogoutHandler(): void
    {
        foreach ($_COOKIE as $name => $value) {
            if (str_starts_with((string)$name, '_shibsession_')) {
                setcookie((string)$name, null, -1, '/');
                break;
            }
        }
    }
}
