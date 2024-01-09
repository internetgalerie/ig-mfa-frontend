<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Event;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class LoginMfaRequiredEvent
{
    private bool $isLoggedIn = false;

    private bool $isMfaFailed = false;

    public function __construct(
        private ServerRequestInterface $request,
        private readonly FrontendUserAuthentication $frontendUserAuthentication,
        private readonly LimiterInterface $rateLimiter,
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getFrontendUserAuthentication(): FrontendUserAuthentication
    {
        return $this->frontendUserAuthentication;
    }

    public function getRateLimiter(): LimiterInterface
    {
        return $this->rateLimiter;
    }

    /**
     * MFA was successful, go on with user login
     */
    public function setIsLoggedIn(bool $isLoggedIn): void
    {
        $this->isLoggedIn = $isLoggedIn;
    }

    public function isLoggedIn(): bool
    {
        return $this->isLoggedIn;
    }

    /**
     * Is MFA missing or failed, redirect according the request
     */
    public function setIsMfaFailed(bool $isMfaFailed): void
    {
        $this->isMfaFailed = $isMfaFailed;
    }

    public function isMfaFailed(): bool
    {
        return $this->isMfaFailed;
    }
}
