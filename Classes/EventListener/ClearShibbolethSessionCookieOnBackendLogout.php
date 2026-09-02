<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\EventListener;

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedOutEvent;

/**
 * Replaces the pre-TYPO3-13 logoff_post_processing hook, which core no
 * longer dispatches (backend logout now only fires this PSR-14 event).
 */
#[AsEventListener(identifier: 'dv-sso-auth/clear-shibboleth-session-cookie-on-backend-logout')]
final class ClearShibbolethSessionCookieOnBackendLogout
{
    public function __construct(private readonly ExtensionSettingsFactory $settingsFactory)
    {
    }

    public function __invoke(AfterUserLoggedOutEvent $event): void
    {
        if (!$event->getUser() instanceof BackendUserAuthentication) {
            return;
        }

        $settings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');
        if (!$settings->enableBE) {
            return;
        }

        foreach (array_keys($_COOKIE) as $name) {
            if (str_starts_with((string)$name, '_shibsession_')) {
                setcookie((string)$name, '', -1, '/');
                break;
            }
        }
    }
}
