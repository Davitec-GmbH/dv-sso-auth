<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Configuration;

final class ExtensionSettings
{
    public function __construct(
        public readonly bool $enableBE,
        public readonly bool $enableFE,
        public readonly bool $enableAutoImport,
        public readonly bool $enableBackendAutoImport,
        public readonly bool $beFetchUserIfNoSession,
        public readonly bool $feFetchUserIfNoSession,
        public readonly bool $forceSSL,
        public readonly bool $onlySsoBE,
        public readonly int $priority,
        public readonly int $storagePid,
        public readonly string $backendAutoImportGroup,
        public readonly string $loginHandler,
        public readonly string $logoutHandler,
        public readonly string $remoteUser,
        public readonly string $mail,
        public readonly string $displayName,
        public readonly string $eduPersonAffiliation,
        public readonly string $typo3LoginTemplate,
    ) {
    }
}
