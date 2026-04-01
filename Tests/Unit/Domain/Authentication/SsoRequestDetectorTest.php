<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Unit\Domain\Authentication;

use Davitec\DvSsoAuth\Domain\Authentication\SsoRequestDetector;
use PHPUnit\Framework\TestCase;

final class SsoRequestDetectorTest extends TestCase
{
    public function testDetectsAuthTypeShibbolethCaseInsensitive(): void
    {
        $detector = new SsoRequestDetector();

        self::assertTrue($detector->isSsoRequest(['AUTH_TYPE' => 'ShIbBoLeTh']));
    }

    public function testDetectsSessionIdentifierFallbacks(): void
    {
        $detector = new SsoRequestDetector();

        self::assertTrue($detector->isSsoRequest(['Shib_Session_ID' => 'abc123']));
        self::assertTrue($detector->isSsoRequest(['REDIRECT_Shib_Session_ID' => 'abc123']));
        self::assertFalse($detector->isSsoRequest(['AUTH_TYPE' => 'basic']));
    }
}
