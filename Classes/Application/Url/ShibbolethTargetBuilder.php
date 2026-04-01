<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Application\Url;

final class ShibbolethTargetBuilder
{
    public function build(string $target, string $storagePid): string
    {
        $targetParts = parse_url($target);
        if ($targetParts === false) {
            return $target;
        }

        parse_str((string)($targetParts['query'] ?? ''), $queryParams);
        unset($queryParams['logintype'], $queryParams['pid']);
        $queryParams['logintype'] = 'login';
        $queryParams['pid'] = $storagePid;

        $scheme = isset($targetParts['scheme']) ? $targetParts['scheme'] . '://' : '';
        $user = $targetParts['user'] ?? '';
        $pass = isset($targetParts['pass']) ? ':' . $targetParts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = $targetParts['host'] ?? '';
        $port = isset($targetParts['port']) ? ':' . $targetParts['port'] : '';
        $path = $targetParts['path'] ?? '/';
        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $fragment = isset($targetParts['fragment']) ? '#' . $targetParts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . ($query !== '' ? '?' . $query : '') . $fragment;
    }
}
