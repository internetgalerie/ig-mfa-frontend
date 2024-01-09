<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Middleware;

use Internetgalerie\IgMfaFrontend\Authentication\MfaFrontendUserAuthentication;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * This middleware authenticates a Frontend User (fe_users) with mfa.
 */
class MfaFrontendUserAuthenticatorSession extends MfaFrontendUserAuthenticator //implements MiddlewareInterface //
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $frontendUser = GeneralUtility::makeInstance(MfaFrontendUserAuthentication::class);
        //$mfaDone = $request->getAttribute('ig-mfa-frontend') ?? false;
        // no user login, handle mfa provider switches at login
        if ($frontendUser->startWithQueryParams($request)) {
            return $this->redirectToMfaAuth($request, $handler, $frontendUser);
        }

        return $handler->handle($request);
    }
 
    /**
     *  Initiate a redirect to MFA action with necessary attributes set
     */
    protected function redirectToMfaAuth(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        FrontendUserAuthentication $frontendUser
    ): ResponseInterface {
        $request = $request->withAttribute('ig-mfa-frontend', true);
        $request = $request->withAttribute('frontend.user', $frontendUser);

        return $handler->handle($request);
    }
}
