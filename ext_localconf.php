<?php

declare(strict_types=1);

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use Davitec\DvSsoAuth\Controller\FrontendLoginController;
use Davitec\DvSsoAuth\Hook\UserAuthentication;
use Davitec\DvSsoAuth\LoginProvider\SsoLoginProvider;
use Davitec\DvSsoAuth\Typo3\Service\SsoAuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

(static function (string $extensionKey = 'dv_sso_auth'): void {
    $settings = GeneralUtility::makeInstance(ExtensionSettingsFactory::class)
        ->createFromExtensionConfiguration($extensionKey);

    $subTypes = [
        'getUserFE',
        'authUserFE',
    ];

    if ($settings->enableBE) {
        $subTypes[] = 'getUserBE';
        $subTypes[] = 'authUserBE';

        $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['BE_fetchUserIfNoSession'] = $settings->beFetchUserIfNoSession;

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_post_processing'][] = UserAuthentication::class . '->backendLogoutHandler';

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1711441200] = [
            'provider' => SsoLoginProvider::class,
            'sorting' => 60,
            'iconIdentifier' => 'actions-key',
            'label' => 'LLL:EXT:dv_sso_auth/Resources/Private/Language/locallang.xlf:backend_login.header',
        ];

        $GLOBALS['TYPO3_CONF_VARS']['BE']['showRefreshLoginPopup'] = 1;
    }

    ExtensionUtility::configurePlugin(
        'DvSsoAuth',
        'Login',
        [
            FrontendLoginController::class => 'index,showLogin,loginSuccess,showLogout,logoutSuccess',
        ],
        [
            FrontendLoginController::class => 'index,showLogin,loginSuccess,showLogout,logoutSuccess',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // Fallback for installations that do not include a "defaultContentRendering" TypoScript set.
    // configurePlugin() injects this object after that include; without it, the CType would be unresolved.
    ExtensionManagementUtility::addTypoScript(
        'dv_sso_auth',
        'setup',
        '
tt_content.dvssoauth_login = EXTBASEPLUGIN
tt_content.dvssoauth_login {
    extensionName = DvSsoAuth
    pluginName = Login
}
'
    );

    $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] = $settings->feFetchUserIfNoSession;

    ExtensionManagementUtility::addService(
        $extensionKey,
        'auth',
        SsoAuthenticationService::class,
        [
            'title' => 'SSO Auth',
            'description' => 'SSO authentication service for TYPO3 frontend and backend.',
            'subtype' => implode(',', $subTypes),
            'available' => true,
            'priority' => $settings->priority,
            'quality' => 50,
            'os' => '',
            'exec' => '',
            'className' => SsoAuthenticationService::class,
        ]
    );
})();
