<?php
defined('TYPO3') || die();

// Service configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'ig_mfa_frontend',
    'auth',
    \Causal\MfaFrontend\Service\MfaAuthenticationService::class,
    [
        'title' => 'MFA Authenticator',
        'description' => 'Enable MFA for frontend login',
        'subtype' => 'getUserFE,authUserFE',
        'available' => true,
        'priority' => 80,
        'quality' => 80,
        'os' => '',
        'exec' => '',
        'className' => \Internetgalerie\IgMfaFrontend\Service\MfaAuthenticationService::class,
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'IgMfaFrontend',
    'MfaConfiguration',
    [
        \Internetgalerie\IgMfaFrontend\Controller\MfaConfigurationController::class => 'overview, setup, activate, deactivate, edit, save, unlock, default'
    ],
    // non-cacheable actions
    [
        \Internetgalerie\IgMfaFrontend\Controller\MfaConfigurationController::class => 'overview, setup, activate, deactivate, edit, save, unlock, default'
    ]
);
