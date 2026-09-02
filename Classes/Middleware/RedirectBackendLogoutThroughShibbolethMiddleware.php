<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Middleware;

use Davitec\DvSsoAuth\Configuration\ExtensionSettingsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * TYPO3's backend LogoutController only destroys the TYPO3 session and
 * redirects straight to the login screen; it has no notion of the
 * Shibboleth SP session, so that survives and immediately re-authenticates
 * the same user. Mirrors the redirect-through-logoutHandler pattern
 * FrontendLoginController::logoutSuccessAction() already uses, applied to
 * the backend logout route instead.
 */
final class RedirectBackendLogoutThroughShibbolethMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ExtensionSettingsFactory $settingsFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($request->getUri()->getPath() !== '/typo3/logout') {
            return $response;
        }

        $settings = $this->settingsFactory->createFromExtensionConfiguration('dv_sso_auth');
        if (!$settings->enableBE) {
            return $response;
        }

        $location = $response->getHeaderLine('location');
        if ($location === '') {
            return $response;
        }

        // "local=true" skips the SAML2 IdP single-logout round trip and forces
        // the SP to tear down its local session unconditionally. Without it,
        // a client with no SLO endpoint configured back to this SP leaves the
        // local session (and thus REMOTE_USER) intact, silently re-authenticating
        // the same user on the very next request.
        $queryStringSeparator = str_contains($settings->logoutHandler, '?') ? '&' : '?';
        $redirectUrl = $settings->logoutHandler . $queryStringSeparator . 'local=true&return=' . rawurlencode($location);

        // Reuse $response rather than building a fresh one: TYPO3's own logout
        // response carries a Set-Cookie header expiring the backend session
        // cookie, and a replacement response would silently drop it.
        return $response
            ->withStatus(303)
            ->withHeader('location', $redirectUrl);
    }
}
