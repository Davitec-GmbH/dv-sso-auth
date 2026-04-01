<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Functional\Configuration;

use Davitec\DvSsoAuth\Typo3\Service\SsoAuthenticationService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExtensionRegistrationTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    protected array $testExtensionsToLoad = [
        '../packages/dv-sso-auth',
    ];

    #[Test]
    public function authServiceIsRegisteredForFrontendByDefault(): void
    {
        $serviceConfiguration = $this->findServiceConfigurationByClassName(
            $GLOBALS['T3_SERVICES']['auth'] ?? [],
            SsoAuthenticationService::class
        );

        self::assertIsArray($serviceConfiguration);

        $subtype = (string)($serviceConfiguration['subtype'] ?? '');
        self::assertStringContainsString('getUserFE', $subtype);
        self::assertStringContainsString('authUserFE', $subtype);
        self::assertStringNotContainsString('getUserBE', $subtype);
        self::assertStringNotContainsString('authUserBE', $subtype);
    }

    /**
     * @param array<mixed> $configuration
     * @return array<string, mixed>|null
     */
    private function findServiceConfigurationByClassName(array $configuration, string $className): ?array
    {
        foreach ($configuration as $value) {
            if (!is_array($value)) {
                continue;
            }

            if (($value['className'] ?? null) === $className) {
                return $value;
            }

            $nestedMatch = $this->findServiceConfigurationByClassName($value, $className);
            if ($nestedMatch !== null) {
                return $nestedMatch;
            }
        }

        return null;
    }
}
