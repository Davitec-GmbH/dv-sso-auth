<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExtensionSettingsFactory
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function createFromArray(array $configuration): ExtensionSettings
    {
        return new ExtensionSettings(
            enableBE: $this->toBool($configuration['enableBE'] ?? false),
            enableFE: $this->toBool($configuration['enableFE'] ?? false),
            enableAutoImport: $this->toBool($configuration['enableAutoImport'] ?? false),
            enableBackendAutoImport: $this->toBool($configuration['enableBackendAutoImport'] ?? false),
            beFetchUserIfNoSession: $this->toBool($configuration['BE_fetchUserIfNoSession'] ?? true),
            feFetchUserIfNoSession: $this->toBool($configuration['FE_fetchUserIfNoSession'] ?? false),
            forceSSL: $this->toBool($configuration['forceSSL'] ?? true),
            onlySsoBE: $this->toBool($configuration['onlySsoBE'] ?? false),
            priority: max(0, min(100, (int)($configuration['priority'] ?? 90))),
            storagePid: max(0, (int)($configuration['storagePid'] ?? 0)),
            backendAutoImportGroup: trim((string)($configuration['backendAutoImportGroup'] ?? '')),
            loginHandler: (string)($configuration['loginHandler'] ?? '/Shibboleth.sso/Login'),
            logoutHandler: (string)($configuration['logoutHandler'] ?? '/Shibboleth.sso/Logout'),
            remoteUser: (string)($configuration['remoteUser'] ?? 'REMOTE_USER'),
            mail: (string)($configuration['mail'] ?? 'mail'),
            displayName: (string)($configuration['displayName'] ?? 'displayName'),
            eduPersonAffiliation: (string)($configuration['eduPersonAffiliation'] ?? 'affiliation'),
            typo3LoginTemplate: (string)($configuration['typo3LoginTemplate'] ?? 'EXT:dv_sso_auth/Resources/Private/Templates/BackendLogin/SsoLogin.html'),
        );
    }

    public function createFromExtensionConfiguration(string $extensionKey): ExtensionSettings
    {
        $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get($extensionKey);

        if (!is_array($configuration)) {
            $configuration = [];
        }

        return $this->createFromArray($configuration);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
