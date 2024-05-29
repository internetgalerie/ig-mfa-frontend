<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;


return [
    'tx-igmfafrontend-svgicon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ig_mfa_frontend/Resources/Public/Icons/Extension.svg',
    ],
];