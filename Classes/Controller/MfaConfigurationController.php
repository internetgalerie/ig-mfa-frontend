<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Controller;

use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaViewType;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * Two-factor authentication setup controller.
 *
 * A controller that allows Frontend users to set up the
 * two-factor authentication for their respective account.
 */
class MfaConfigurationController extends AbstractMfaController
{
    protected function initializeAction(): void
    {
        MfaUtility::initializeMfaConfiguration();
    }

    /**
     * Setup the overview with all available MFA providers
     */
    public function overviewAction(): ResponseInterface
    {
        $this->view->assignMultiple([
            'providers' => MfaUtility::getAllowedProviders(),
            'defaultProvider' => $this->getDefaultProviderIdentifier(),
            'recommendedProvider' => $this->getRecommendedProviderIdentifier(),
            'setupRequired' => MfaUtility::getMfaRequired() && !$this->mfaProviderRegistry->hasActiveProviders(
                MfaUtility::getFrontendUserAuthentication()
            ),
        ]);
        return $this->htmlResponse($this->view->render());
    }

    /**
     * Render form to setup a provider by using provider specific content
     */
    public function setupAction(string $identifier): ResponseInterface
    {
        $mfaProvider = $this->getProviderByIdentifier($identifier);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        // get sitename from current Site
        $backupSitename = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        $site = $this->request->getAttribute('site', null);
        $typoscriptName = (string) $this->getSettings()['name'] ?? '';
        $sitename = (string)($typoscriptName ?: $site->getAttribute('websiteTitle'));
        if ($sitename !== '') {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = $sitename;
        }

        $providerResponse = $mfaProvider->handleRequest($this->request, $propertyManager, MfaViewType::SETUP);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = $backupSitename;

        $this->view->assignMultiple([
            'provider' => $mfaProvider,
            'providerContent' => $providerResponse->getBody(),
        ]);
        //if ($this->settings['jsFile']) {
        //    $this->assetCollector->addJavaScript('ig-mfa-frontend', $this->settings['jsFile']);
        //}
        return $this->htmlResponse($this->view->render());
    }

    /**
     * Render form to setup a provider by using provider specific content
     */
    public function activateAction(string $identifier): ResponseInterface
    {
        $frontendUser = MfaUtility::getFrontendUserAuthentication();
        $mfaProvider = $this->getProviderByIdentifier($identifier, false);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $isRecommendedProvider = $this->getRecommendedProviderIdentifier() === $mfaProvider->getIdentifier();
        $propertyManager = MfaProviderPropertyManager::create($mfaProvider, $frontendUser);
        $languageService = $this->getLanguageService();
        // Check whether activation operation was successful and the provider is now active.
        if (!$mfaProvider->activate($this->request, $propertyManager) || !$mfaProvider->isActive($propertyManager)) {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL(
                        'LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:activate.failure'
                    ),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('setup', null, null, [
                'identifier' => $mfaProvider->getIdentifier(),
            ]);
        }

        if ($isRecommendedProvider
            || (
                $this->getDefaultProviderIdentifier() === ''
                && $mfaProvider->isDefaultProviderAllowed()
                && !$this->hasSuitableDefaultProviders([$mfaProvider->getIdentifier()])
            )
        ) {
            $this->setDefaultProvider($mfaProvider);
        }

        // If this is the first activated provider, the user has logged in without being required
        // to pass the MFA challenge. Therefore, no session entry exists. To prevent the challenge
        // from showing up after the activation we need to set the session data here.
        if (!(bool)($frontendUser->getSessionData('mfa') ?? false)) {
            $frontendUser->setSessionData('mfa', true);
        }

        $this->addFlashMessage(
            sprintf(
                $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:activate.success'),
                $languageService->sL($mfaProvider->getTitle())
            ),
            '',
            ContextualFeedbackSeverity::OK
        );
        return new ForwardResponse('overview');
    }

    /**
     * Render form to setup a provider by using provider specific content
     */
    public function deactivateAction(string $identifier): ResponseInterface
    {
        $mfaProvider = $this->getProviderByIdentifier($identifier, true);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        $languageService = $this->getLanguageService();
        if (!$mfaProvider->deactivate($this->request, $propertyManager)) {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL(
                        'LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:deactivate.failure'
                    ),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            if ($this->isDefaultProvider($mfaProvider)) {
                $this->removeDefaultProvider();
            }

            $this->addFlashMessage(
                sprintf(
                    $languageService->sL(
                        'LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:deactivate.success'
                    ),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        return new ForwardResponse('overview');
    }

    /**
     * Handle unlock request by forwarding the request to the appropriate provider
     */
    public function unlockAction(string $identifier): ResponseInterface
    {
        $mfaProvider = $this->getProviderByIdentifier($identifier, true);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        $languageService = $this->getLanguageService();

        if (!$mfaProvider->unlock($this->request, $propertyManager)) {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:unlock.failure'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:unlock.success'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        return new ForwardResponse('overview');
    }

    /**
     * Render form to edit a provider by using provider specific content
     */
    public function editAction(string $identifier): ResponseInterface
    {
        ///$this->controllerContext->getFlashMessageQueue('core.template.flashMessages')->enqueue($message);
        $mfaProvider = $this->getProviderByIdentifier($identifier, true);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        if ($mfaProvider->isLocked($propertyManager)) {
            // Do not show edit view for locked providers
            $this->addFlashMessage(
                $this->getLanguageService()
->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:providerIsLocked'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return new ForwardResponse('overview');
        }

        $providerResponse = $mfaProvider->handleRequest($this->request, $propertyManager, MfaViewType::EDIT);
        $this->view->assignMultiple([
            'provider' => $mfaProvider,
            'providerContent' => $providerResponse->getBody(),
            'isDefaultProvider' => $this->isDefaultProvider($mfaProvider),
        ]);
        return $this->htmlResponse($this->view->render());
    }

    /**
     * Handle save request, receiving from the edit view by
     * forwarding the request to the appropriate provider.
     */
    public function saveAction(string $identifier): ResponseInterface
    {
        $mfaProvider = $this->getProviderByIdentifier($identifier, true);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        $languageService = $this->getLanguageService();
        if (!$mfaProvider->update($this->request, $propertyManager)) {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:save.failure'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            if ($this->request->getParsedBody()['defaultProvider'] ?? false) {
                $this->setDefaultProvider($mfaProvider);
            } elseif ($this->isDefaultProvider($mfaProvider)) {
                $this->removeDefaultProvider();
            }

            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:save.success'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::OK
            );
        }

        if (!$mfaProvider->isActive($propertyManager)) {
            return new ForwardResponse('overview');
        }

        return $this->redirect('edit', null, null, [
            'identifier' => $mfaProvider->getIdentifier(),
        ]);
    }

    /**
     * set the given provider as default
     */
    public function defaultAction(string $identifier): ResponseInterface
    {
        $mfaProvider = $this->getProviderByIdentifier($identifier, true);
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return new ForwardResponse('overview');
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $mfaProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        $languageService = $this->getLanguageService();
        if ($mfaProvider->isActive($propertyManager) && $mfaProvider->isDefaultProviderAllowed()) {
            $this->setDefaultProvider($mfaProvider);
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:save.success'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::OK
            );
        } else {
            $this->addFlashMessage(
                sprintf(
                    $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:save.failure'),
                    $languageService->sL($mfaProvider->getTitle())
                ),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        return new ForwardResponse('overview');
    }

    protected function getProviderByIdentifier(
        string $identifier,
        ?bool $neededProviderActive = null
    ): ?MfaProviderManifestInterface {
        $mfaProvider = null;
        if (MfaUtility::isValidIdentifier($identifier)) {
            $mfaProvider = $this->mfaProviderRegistry->getProvider($identifier);
        }

        // All actions expect "overview" require a provider to deal with.
        // If non is found at this point, initiate a redirect to the overview.
        if (!$mfaProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            $this->addFlashMessage(
                $this->getLanguageService()
->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:providerNotFound'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return null;
        }

        if ($neededProviderActive !== null) {
            // If a valid provider is given, check if the requested action can be performed on this provider
            $isProviderActive = $mfaProvider->isActive(
                MfaProviderPropertyManager::create($mfaProvider, MfaUtility::getFrontendUserAuthentication())
            );
            // Some actions require the provider to be active or inactive
            if ($isProviderActive !== $neededProviderActive) {
                if ($isProviderActive) {
                    $this->addFlashMessage(
                        $this->getLanguageService()
->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:providerActive'),
                        '',
                        ContextualFeedbackSeverity::ERROR
                    );
                } else {
                    $this->addFlashMessage(
                        $this->getLanguageService()
->sL('LLL:EXT:backend/Resources/Private/Language/locallang_mfa.xlf:providerNotActive'),
                        '',
                        ContextualFeedbackSeverity::ERROR
                    );
                }

                return null;
            }
        }

        return $mfaProvider;
    }

    /**
     * Check if there are more suitable default providers for the current user
     */
    protected function hasSuitableDefaultProviders(array $excludedProviders = []): bool
    {
        foreach (MfaUtility::getAllowedProviders() as $identifier => $provider) {
            if (!in_array($identifier, $excludedProviders, true)
                && $provider->isDefaultProviderAllowed()
                && $provider->isActive(
                    MfaProviderPropertyManager::create($provider, MfaUtility::getFrontendUserAuthentication())
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default provider
     */
    protected function getDefaultProviderIdentifier(): string
    {
        $defaultProviderIdentifier = (string)(MfaUtility::getFrontendUserAuthentication()->uc['mfa']['defaultProvider'] ?? '');
        // The default provider value is only valid, if the corresponding provider exist and is allowed
        if (MfaUtility::isValidIdentifier($defaultProviderIdentifier)) {
            $defaultProvider = $this->mfaProviderRegistry->getProvider($defaultProviderIdentifier);
            $propertyManager = MfaProviderPropertyManager::create(
                $defaultProvider,
                MfaUtility::getFrontendUserAuthentication()
            );
            // Also check if the provider is activated for the user
            if ($defaultProvider->isActive($propertyManager)) {
                return $defaultProviderIdentifier;
            }
        }

        // If the stored provider is not valid, clean up the UC
        $this->removeDefaultProvider();
        return '';
    }

    /**
     * Get the recommended provider
     */
    protected function getRecommendedProviderIdentifier(): string
    {
        $recommendedProvider = $this->getRecommendedProvider();
        if (!$recommendedProvider instanceof \TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface) {
            return '';
        }

        $propertyManager = MfaProviderPropertyManager::create(
            $recommendedProvider,
            MfaUtility::getFrontendUserAuthentication()
        );
        // If the defined recommended provider is valid, check if it is not yet activated
        return $recommendedProvider->isActive($propertyManager) ? '' : $recommendedProvider->getIdentifier();
    }

    /*
    protected function removeImportmap(string $html)
    {
        // Find the position of the opening script tag
        $startPos = strpos($html, '<script type="importmap">');

        // If the opening script tag is found, find the position of the closing script tag
        if ($startPos !== false) {
            $endPos = strpos($html, '</script>', $startPos);

            // If the closing script tag is found, remove the script block
            if ($endPos !== false) {
                $html = substr_replace($html, '', $startPos, $endPos + 9 - $startPos); // 9 is the length of </script>
            }
        }
        return $html;
    }
    */

    protected function isDefaultProvider(MfaProviderManifestInterface $mfaProvider): bool
    {
        return $this->getDefaultProviderIdentifier() === $mfaProvider->getIdentifier();
    }

    protected function setDefaultProvider(MfaProviderManifestInterface $mfaProvider): void
    {
        MfaUtility::getFrontendUserAuthentication()->uc['mfa']['defaultProvider'] = $mfaProvider->getIdentifier();
        MfaUtility::getFrontendUserAuthentication()->writeUC();
    }

    protected function removeDefaultProvider(): void
    {
        if (!is_array(MfaUtility::getFrontendUserAuthentication()->uc['mfa'] ?? false)) {
            MfaUtility::getFrontendUserAuthentication()->uc['mfa'] = [];
        }

        MfaUtility::getFrontendUserAuthentication()->uc['mfa']['defaultProvider'] = '';
        MfaUtility::getFrontendUserAuthentication()->writeUC();
    }
}
