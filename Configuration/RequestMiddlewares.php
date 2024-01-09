<?php
    return     [
        'frontend' => [
            'internetgalerie/ig-mfa-frontend/mfa-authentication-session' => [
                'target' => \Internetgalerie\IgMfaFrontend\Middleware\MfaFrontendUserAuthenticatorSession::class,
                'before' => [
                    'typo3/cms-frontend/tsfe',
                ],
                'after' => [
                    'typo3/cms-frontend/authentication',
                    'typo3/cms-frontend/maintenance-mode',
                    'typo3/cms-frontend/site',
                    'typo3/cms-core/request-token-middleware',
                ]
            ],
            'typo3/cms-frontend/authentication' => [
                'target' => \Internetgalerie\IgMfaFrontend\Middleware\MfaFrontendUserAuthenticator::class,
                'before' => [
                    'typo3/cms-frontend/tsfe',
                    //'typo3/cms-frontend/authentication',
                ],
                'after' => [
                    'typo3/cms-frontend/maintenance-mode',
                    'typo3/cms-frontend/site',
                ]
            ],
        ],
];