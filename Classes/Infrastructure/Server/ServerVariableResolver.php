<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Infrastructure\Server;

final class ServerVariableResolver
{
    /**
     * @param array<string, mixed> $server
     */
    public function resolve(array $server, string $key, string $prefix = 'REDIRECT_'): ?string
    {
        if (array_key_exists($key, $server) && is_scalar($server[$key])) {
            return (string)$server[$key];
        }

        $prefixedKey = $prefix . $key;
        if (array_key_exists($prefixedKey, $server) && is_scalar($server[$prefixedKey])) {
            return (string)$server[$prefixedKey];
        }

        foreach ($server as $candidateKey => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if ($key === str_replace($prefix, '', (string)$candidateKey)) {
                return (string)$value;
            }
        }

        return null;
    }
}
