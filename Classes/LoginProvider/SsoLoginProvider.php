<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\LoginProvider;

use Davitec\DvSsoAuth\Configuration\ExtensionSettings;
use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;

final class SsoLoginProvider implements LoginProviderInterface
{
    public function __construct(private readonly ExtensionSettingsFactory $settingsFactory)
    {
    }

    /**
     * TYPO3 12/13 login provider API.
     *
     * @param mixed $view
     */
    public function render($view, $pageRenderer, $loginController): void
    {
        $settings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');
        $templatePath = $this->resolveTemplatePath($settings);
        $ssoLoginUri = $this->buildSsoLoginUri($settings);

        if (\is_object($view) && method_exists($view, 'setTemplatePathAndFilename')) {
            $view->setTemplatePathAndFilename($templatePath);
        }
        if (\is_object($view) && method_exists($view, 'assign')) {
            $view->assign('ssoLoginUri', $ssoLoginUri);
        }
    }

    /**
     * Alternative login provider API using request and view arguments.
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $settings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');
        $templatePath = $this->resolveTemplatePath($settings);
        $view->assign('ssoLoginUri', $this->buildSsoLoginUri($settings));
        return $templatePath;
    }

    private function buildSsoLoginUri(ExtensionSettings $settings): string
    {
        $target = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/typo3/?login_status=login';
        if ($settings->forceSSL && !GeneralUtility::getIndpEnv('TYPO3_SSL')) {
            $target = str_ireplace('http:', 'https:', $target);
        }

        $queryStringSeparator = str_contains($settings->loginHandler, '?') ? '&' : '?';
        return $settings->loginHandler . $queryStringSeparator . 'target=' . rawurlencode($target);
    }

    private function resolveTemplatePath(ExtensionSettings $settings): string
    {
        $templatePath = $settings->typo3LoginTemplate;
        if ($templatePath === '') {
            $templatePath = 'EXT:dv_sso_auth/Resources/Private/Templates/BackendLogin/SsoLogin.html';
        }
        return GeneralUtility::getFileAbsFileName($templatePath);
    }
}
