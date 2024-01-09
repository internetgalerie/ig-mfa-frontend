<?php
defined('TYPO3') || die();

$tempColumns = [

    'mfa' => [
        'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_users.mfa',
        'config' => [
            // @todo Use the new internal TCA type when available
            'type' => 'none',
            'renderType' => 'mfaInfo',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'fe_users',
    $tempColumns
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'mfa',
    '',
    'after:password' // Add the 2FA after our custom field "password"
);