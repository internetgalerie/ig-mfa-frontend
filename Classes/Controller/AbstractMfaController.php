<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Controller;

use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class AbstractMfaController extends ActionController
{
    protected MfaProviderRegistry $mfaProviderRegistry;

    protected static ?array $mfaSettings = null;

    public function injectMfaProviderRegistry(MfaProviderRegistry $mfaProviderRegistry): void
    {
        $this->mfaProviderRegistry = $mfaProviderRegistry;
    }

    /**
     * Get the recommended provider
     */
    protected function getRecommendedProvider(): ?MfaProviderManifestInterface
    {
        $recommendedProviderIdentifier = (string)($this->getSettings()['recommendedMfaProvider'] ?? '');

        // Check if valid and allowed to be default provider, which is obviously a prerequisite
        if (!MfaUtility::isValidIdentifier($recommendedProviderIdentifier)
            || !$this->mfaProviderRegistry->getProvider($recommendedProviderIdentifier)
->isDefaultProviderAllowed()
        ) {
            return null;
        }

        return $this->mfaProviderRegistry->getProvider($recommendedProviderIdentifier);
    }

    protected function getSettings(): array
    {
        if (static::$mfaSettings === null) {
            $all = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            static::$mfaSettings = $all['plugin.']['tx_igmfafrontend_mfaconfiguration.']['settings.'] ?? [];
        }

        return static::$mfaSettings;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'] ?? GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }
}
