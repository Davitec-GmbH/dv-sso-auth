<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Middleware;

use Davitec\DvSsoAuth\Domain\Authentication\SsoRequestDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class ResetBrokenFrontendSessionMiddleware implements MiddlewareInterface
{
    private const RETRY_FLAG = 'ssoSessionRetry';

    public function __construct(private readonly SsoRequestDetector $requestDetector)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieName = FrontendUserAuthentication::getCookieName();

        if (!isset($request->getCookieParams()[$cookieName])) {
            return $handler->handle($request);
        }

        if (!$this->shouldHandleRequest($request)) {
            return $handler->handle($request);
        }

        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 403) {
            return $response;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            $normalizedParams = NormalizedParams::createFromRequest($request);
        }

        $expiredCookie = SetCookieService::create($cookieName, 'FE')->removeCookie($normalizedParams);

        if ($this->isRetryRequest($request)) {
            return $response->withAddedHeader('Set-Cookie', $expiredCookie->__toString());
        }

        $retryUri = $this->buildRetryUri($request);

        return new Response(
            'php://temp',
            303,
            [
                'Location' => (string)$retryUri,
                'Set-Cookie' => $expiredCookie->__toString(),
            ]
        );
    }

    private function buildRetryUri(ServerRequestInterface $request): string
    {
        $queryParams = $request->getQueryParams();
        $queryParams[self::RETRY_FLAG] = '1';

        $uri = $request->getUri()->withQuery(http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986));

        return (string)$uri;
    }

    private function isRetryRequest(ServerRequestInterface $request): bool
    {
        $parsedBody = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $queryParams = $request->getQueryParams();
        $retryFlag = (string)($parsedBody[self::RETRY_FLAG] ?? $queryParams[self::RETRY_FLAG] ?? '');

        return $retryFlag === '1';
    }

    private function shouldHandleRequest(ServerRequestInterface $request): bool
    {
        $parsedBody = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $queryParams = $request->getQueryParams();
        $loginType = (string)($parsedBody['logintype'] ?? $queryParams['logintype'] ?? '');

        if ($loginType === 'login') {
            return true;
        }

        return $this->requestDetector->isSsoRequest($request->getServerParams());
    }
}
