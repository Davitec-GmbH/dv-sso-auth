<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Unit\Configuration;

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use PHPUnit\Framework\TestCase;

final class ExtensionSettingsFactoryTest extends TestCase
{
    public function testDefaultsAreAppliedWhenValuesAreMissing(): void
    {
        $factory = new ExtensionSettingsFactory();

        $settings = $factory->createFromArray([]);

        self::assertFalse($settings->enableBE);
        self::assertFalse($settings->enableFE);
        self::assertTrue($settings->beFetchUserIfNoSession);
        self::assertFalse($settings->feFetchUserIfNoSession);
        self::assertSame(90, $settings->priority);
        self::assertSame('REMOTE_USER', $settings->remoteUser);
        self::assertSame('/Shibboleth.sso/Login', $settings->loginHandler);
    }

    public function testBooleanAndNumericValuesAreNormalized(): void
    {
        $factory = new ExtensionSettingsFactory();

        $settings = $factory->createFromArray([
            'enableBE' => '1',
            'enableFE' => 'true',
            'enableAutoImport' => 'yes',
            'enableBackendAutoImport' => 1,
            'BE_fetchUserIfNoSession' => 'on',
            'FE_fetchUserIfNoSession' => 0,
            'forceSSL' => '0',
            'onlySsoBE' => '1',
            'priority' => 123,
            'storagePid' => -15,
        ]);

        self::assertTrue($settings->enableBE);
        self::assertTrue($settings->enableFE);
        self::assertTrue($settings->enableAutoImport);
        self::assertTrue($settings->enableBackendAutoImport);
        self::assertTrue($settings->beFetchUserIfNoSession);
        self::assertFalse($settings->feFetchUserIfNoSession);
        self::assertFalse($settings->forceSSL);
        self::assertTrue($settings->onlySsoBE);
        self::assertSame(100, $settings->priority);
        self::assertSame(0, $settings->storagePid);
    }
}
