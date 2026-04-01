<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Unit\Application\Url;

use Davitec\DvSsoAuth\Application\Url\ShibbolethTargetBuilder;
use PHPUnit\Framework\TestCase;

final class ShibbolethTargetBuilderTest extends TestCase
{
    public function testAddsLoginTypeAndPidToUrl(): void
    {
        $builder = new ShibbolethTargetBuilder();

        $result = $builder->build('https://example.org/protected/page?foo=bar', '12@hmac');

        self::assertStringContainsString('foo=bar', $result);
        self::assertStringContainsString('logintype=login', $result);
        self::assertStringContainsString('pid=12%40hmac', $result);
    }

    public function testReplacesExistingLoginParametersAndKeepsFragment(): void
    {
        $builder = new ShibbolethTargetBuilder();

        $result = $builder->build('https://example.org/path?logintype=logout&pid=1#frag', '7');

        self::assertStringContainsString('logintype=login', $result);
        self::assertStringContainsString('pid=7', $result);
        self::assertStringContainsString('#frag', $result);
    }
}
