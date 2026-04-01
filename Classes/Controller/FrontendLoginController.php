<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Controller;

use Davitec\DvSsoAuth\Application\Url\ShibbolethTargetBuilder;
use Davitec\DvSsoAuth\Configuration\ExtensionSettings;
use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class FrontendLoginController extends ActionController
{
    private ?ExtensionSettings $extensionSettings = null;

    public function __construct(
        private readonly Context $context,
        private readonly Features $features,
        private readonly ExtensionSettingsFactory $settingsFactory,
        private readonly ShibbolethTargetBuilder $targetBuilder,
    ) {
    }

    public function initializeAction(): void
    {
        $this->extensionSettings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');
    }

    /**
     * @throws AspectNotFoundException
     */
    public function indexAction(): ResponseInterface
    {
        $loginType = (string)($this->request->getParsedBody()['logintype'] ?? $this->request->getQueryParams()['logintype'] ?? '');
        $redirectUrl = (string)($this->request->getParsedBody()['redirect_url'] ?? $this->request->getQueryParams()['redirect_url'] ?? '');
        $userIsLoggedIn = (bool)$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn');

        if ($userIsLoggedIn && $loginType !== 'logout') {
            if ($redirectUrl !== '' || $this->getConfiguredRedirectPage() !== null) {
                return new ForwardResponse('loginSuccess');
            }

            return new ForwardResponse('showLogout');
        }

        if ($loginType === 'logout') {
            return new ForwardResponse('logoutSuccess');
        }

        if ($loginType === 'login') {
            $normalizedParams = $this->request->getAttribute('normalizedParams');
            $host = $normalizedParams->getRequestHost();
            $path = parse_url($normalizedParams->getRequestUrl(), PHP_URL_PATH);
            $queryParams = $this->request->getQueryParams();
            unset($queryParams['logintype'], $queryParams['pid']);
            $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
            $uri = $host . $path . ($queryString !== '' ? '?' . $queryString : '');

            return $this->responseFactory()
                ->createResponse(HttpUtility::HTTP_STATUS_303)
                ->withHeader('Location', $uri);
        }

        return new ForwardResponse('showLogin');
    }

    /**
     * @throws PropagateResponseException
     */
    public function showLoginAction(): ResponseInterface
    {
        $settings = $this->getExtensionSettings();
        $target = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');

        if ($settings->forceSSL && !GeneralUtility::getIndpEnv('TYPO3_SSL')) {
            $target = str_ireplace('http:', 'https:', $target);

            if (strpbrk($target, "\"<>\\") === false) {
                $response = $this->responseFactory()
                    ->createResponse(HttpUtility::HTTP_STATUS_303)
                    ->withAddedHeader('location', $target);

                throw new PropagateResponseException($response, 1738800643);
            }
        }

        $target = $this->targetBuilder->build($target, $this->resolveLoginStoragePid());

        $queryStringSeparator = str_contains($settings->loginHandler, '?') ? '&' : '?';
        $shibbolethLoginUri = $settings->loginHandler . $queryStringSeparator . 'target=' . rawurlencode($target);
        $shibbolethLoginUri = GeneralUtility::sanitizeLocalUrl($shibbolethLoginUri);

        $this->view->assign('shibbolethLoginUri', $shibbolethLoginUri);

        return $this->htmlResponse();
    }

    public function loginSuccessAction(): ResponseInterface
    {
        $settings = $this->getExtensionSettings();
        $redirectUrl = (string)($this->request->getParsedBody()['redirect_url'] ?? $this->request->getQueryParams()['redirect_url'] ?? '');
        $targetUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . $redirectUrl;
        $targetUrl = GeneralUtility::sanitizeLocalUrl($targetUrl);

        $configuredRedirectPage = $this->getConfiguredRedirectPage();
        if ($redirectUrl === '' && $configuredRedirectPage !== null) {
            $absoluteUriScheme = $settings->forceSSL ? 'https' : 'http';
            $targetUrl = $this->uriBuilder
                ->setTargetPageUid($configuredRedirectPage)
                ->setAbsoluteUriScheme($absoluteUriScheme)
                ->setCreateAbsoluteUri(true)
                ->build();
        }

        return $this->responseFactory()->createResponse(303)->withAddedHeader('location', $targetUrl);
    }

    public function showLogoutAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

    public function logoutSuccessAction(): ResponseInterface
    {
        if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->fe_user instanceof FrontendUserAuthentication) {
            $GLOBALS['TSFE']->fe_user->logoff();
        }

        $settings = $this->getExtensionSettings();
        $returnUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/';
        $queryStringSeparator = str_contains($settings->logoutHandler, '?') ? '&' : '?';
        $redirectUrl = $settings->logoutHandler . $queryStringSeparator . 'return=' . urlencode($returnUrl);

        return $this->responseFactory()
            ->createResponse(303)
            ->withAddedHeader('location', $redirectUrl);
    }

    private function getConfiguredRedirectPage(): ?int
    {
        if (!array_key_exists('redirectPage', $this->settings) || $this->settings['redirectPage'] === '') {
            return null;
        }

        return (int)$this->settings['redirectPage'];
    }

    private function resolveLoginStoragePid(): string
    {
        $storagePid = (string)$this->getExtensionSettings()->storagePid;

        if (!$this->shallEnforceLoginSigning()) {
            return $storagePid;
        }

        return sprintf(
            '%s@%s',
            $storagePid,
            GeneralUtility::hmac($storagePid, FrontendUserAuthentication::class)
        );
    }

    private function shallEnforceLoginSigning(): bool
    {
        return $this->features->isFeatureEnabled('security.frontend.enforceLoginSigning');
    }

    private function getExtensionSettings(): ExtensionSettings
    {
        if ($this->extensionSettings instanceof ExtensionSettings) {
            return $this->extensionSettings;
        }

        $this->extensionSettings = $this->settingsFactory->createFromArray([]);

        return $this->extensionSettings;
    }

    private function responseFactory(): ResponseFactoryInterface
    {
        return GeneralUtility::makeInstance(ResponseFactoryInterface::class);
    }
}
