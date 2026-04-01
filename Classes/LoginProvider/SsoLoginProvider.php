<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\LoginProvider;

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

final class SsoLoginProvider implements LoginProviderInterface
{
    public function __construct(private readonly ExtensionSettingsFactory $settingsFactory)
    {
    }

    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $settings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');

        $templatePath = $settings->typo3LoginTemplate;
        if ($templatePath === '') {
            $templatePath = 'EXT:dv_sso_auth/Resources/Private/Templates/BackendLogin/SsoLogin.html';
        }

        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePath));

        $target = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/typo3/?login_status=login';
        if ($settings->forceSSL && !GeneralUtility::getIndpEnv('TYPO3_SSL')) {
            $target = str_ireplace('http:', 'https:', $target);
        }

        $queryStringSeparator = str_contains($settings->loginHandler, '?') ? '&' : '?';
        $ssoLoginUri = $settings->loginHandler . $queryStringSeparator . 'target=' . rawurlencode($target);

        $view->assign('ssoLoginUri', $ssoLoginUri);
    }
}
