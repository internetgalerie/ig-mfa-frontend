<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\EventListener;

use Internetgalerie\IgMfaFrontend\Event\LoginMfaRequiredEvent;
use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final readonly class LoginMfaRequiredEventListener
{
    private MfaProviderRegistry $mfaProviderRegistry;

    public function __invoke(LoginMfaRequiredEvent $event): void
    {
        $frontendUser = $event->getFrontendUserAuthentication();
        $request = $event->getRequest();
        $rateLimiter = $event->getRateLimiter();
        MfaUtility::initializeMfaConfiguration($frontendUser);
        // no mfa login
        $identifier = MfaUtility::getProviderIdentifierByRequest($request);

        if ($identifier === '') {
            $mfaProvider = GeneralUtility::makeInstance(
                MfaProviderRegistry::class
            )->getFirstAuthenticationAwareProvider($frontendUser);
            $propertyManager = MfaProviderPropertyManager::create($mfaProvider, $frontendUser);
            // reset rateLimiter
            if ($rateLimiter) {
                $rateLimiter->reset();
            }

            $event->setRequest($this->enrichRequestWithMfaAuth($request, $frontendUser));
            $event->setIsMfaFailed(true);
            return;
        }

        $mfaProvider = MfaUtility::getProviderByIdentifier($identifier);
        $propertyManager = MfaProviderPropertyManager::create($mfaProvider, $frontendUser);

        if (!$mfaProvider->verify($request, $propertyManager)) {
            $event->setRequest($this->enrichRequestWithMfaAuth($request, $frontendUser));
            $event->setIsMfaFailed(true);
            return;
        }

        // login success with mfa
        $frontendUser->setKey('ses', 'mfa', true);
        $event->setIsLoggedIn(true);
    }
    
    private function enrichRequestWithMfaAuth(
        ServerRequestInterface $request,
        FrontendUserAuthentication $frontendUser
    ): ServerRequestInterface {
        $request = $request->withAttribute('ig-mfa-frontend', true);
        return $request->withAttribute('frontend.user', $frontendUser);
    }
}
