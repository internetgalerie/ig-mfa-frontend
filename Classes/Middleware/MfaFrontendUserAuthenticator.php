<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Middleware;

use Internetgalerie\IgMfaFrontend\Event\LoginMfaRequiredEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Middleware\FrontendUserAuthenticator;

/**
 * This middleware authenticates a Frontend User (fe_users) with mfa.
 */
class MfaFrontendUserAuthenticator extends FrontendUserAuthenticator
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        // Rate Limiting
        $rateLimiter = $this->ensureLoginRateLimit($frontendUser, $request);

        // Authenticate now
        // IG BEGIN
        try {
            $frontendUser->start($request);
        } catch (MfaRequiredException $mfaRequiredException) {
            /**
             * first authentication method (username+password
             * or any other authentication token) was successful
             * and mfa is required
             */
            $event = GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch(
                new LoginMfaRequiredEvent($request, $frontendUser, $rateLimiter)
            );
            if ($event->isMfaFailed() === true) {
                return $handler->handle($event->getRequest());
            } elseif ($event->isLoggedIn() !== true) {
                throw $mfaRequiredException;
            }
        }

        // IG END

        // no matter if we have an active user we try to fetch matching groups which can
        // be set without an user (simulation for instance!)
        $frontendUser->fetchGroupData($request);

        // Register the frontend user as aspect and within the request
        $this->context->setAspect('frontend.user', $frontendUser->createUserAspect());
        $request = $request->withAttribute('frontend.user', $frontendUser);

        if ($this->context->getAspect('frontend.user')->isLoggedIn() && $rateLimiter) {
            $rateLimiter->reset();
        }

        $response = $handler->handle($request);

        // Store session data for fe_users if it still exists
        if ($frontendUser instanceof FrontendUserAuthentication) {
            $frontendUser->storeSessionData();
            $response = $frontendUser->appendCookieToResponse($response, $request->getAttribute('normalizedParams'));
            // Collect garbage in Frontend requests, which aren't fully cacheable (e.g. with cookies)
            if ($response->hasHeader('Set-Cookie')) {
                $this->sessionGarbageCollection();
            }
        }

        return $response;
    }
}
