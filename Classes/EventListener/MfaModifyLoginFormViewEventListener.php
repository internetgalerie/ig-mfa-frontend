<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\EventListener;

use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Authentication\Mfa\MfaViewType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

final class MfaModifyLoginFormViewEventListener
{
    private MfaProviderRegistry $mfaProviderRegistry;

    public function __invoke(ModifyLoginFormViewEvent $event): void
    {
        $view = $event->getView();
        $renderingContext = $view->getRenderingContext();
        $request = $renderingContext->getRequest();
        $frontendUser = $request->getAttribute('frontend.user');
        $newToken = $request->getAttribute('ig-mfa-frontend') ?? false;
        //$requestUsername = $request->getParsedBody()['user'] ?? $request->getQueryParams()['user'] ?? null;
        $mfa = [];

        if ($newToken) {
            $view->setTemplate('Mfa');
            // create token for user
            $requestToken = MfaUtility::createToken([
                'username' => $frontendUser->user['username'],
            ]);
            $hashSignedJwt = MfaUtility::getHashSignedJwtByToken($requestToken);

            // get current provider
            $mfaProvider = null;
            $identifier = MfaUtility::getProviderIdentifierByRequest($request);
            $this->mfaProviderRegistry = GeneralUtility::makeInstance(MfaProviderRegistry::class);
            MfaUtility::initializeMfaConfiguration();
            if ($identifier === '' || !MfaUtility::isValidIdentifier($identifier)) {
                $mfaProvider = $this->mfaProviderRegistry->getFirstAuthenticationAwareProvider(
                    MfaUtility::getFrontendUserAuthentication()
                );
            //$recommendedProviderIdentifier = (string)(static::$mfaSettings['recommendedMfaProvider'] ?? '');
            } else {
                $mfaProvider = MfaUtility::getProviderByIdentifier($identifier);
            }

            // generate html output for current provider
            if ($mfaProvider) {
                $propertyManager = MfaProviderPropertyManager::create(
                    $mfaProvider,
                    MfaUtility::getFrontendUserAuthentication()
                );
                // propertyManager->getProperty('userEntity') must be set
                $providerResponse = $mfaProvider->handleRequest($request, $propertyManager, MfaViewType::AUTH);
                $providerContent = $providerResponse->getBody();
                $providerAttempts = [
                    'current' => (int)$propertyManager->getProperty('attempts', 0) + 1,
                    'failed' => (int)$propertyManager->getProperty('attempts', 0),
                    'max' => $mfaProvider->getIdentifier() == 'totp' ? 3 : 0, // MAX_ATTEMPTS of TotpProvider (public getter in interface?)

                ];
                $alternativeProviders = MfaUtility::getAlternativeProviders($mfaProvider);
            } else {
                $mfaProvider = null;
                $providerContent = '';
                $providerAttempts = [];
            }

            // set data for template
            $user = $frontendUser ? $frontendUser->user['username'] ?? '' : '';
            $mfa['user'] = $user;
            $mfa['requestToken'] = [
                'name' => MfaUtility::PARAM_NAME,
                'value' => (string)$hashSignedJwt,
            ];
            $view->assignMultiple([
                'provider' => $mfaProvider,
                'alternativeProviders' => $alternativeProviders,
                'providerContent' => $providerContent,
                'providerAttempts' => $providerAttempts,
                'mfa' => $mfa,
            ]);
        } else {
            // @todo needed? move to auth?
            // unset mfa in normal login so mfa is required again
            $user = MfaUtility::getFrontendUserAuthentication();
            if ((bool)$user->getKey('ses', 'mfa')) {
                $user->setAndSaveSessionData('mfa', false);
            }
        }
    }
}
