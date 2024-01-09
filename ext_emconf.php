<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'MFA for Frontend',
    'description' => 'MFA Login and Configuration for Frontend Users',
    'category' => 'plugin',
    'author' => 'Daniel Abplanalp, Noah Grossen',
    'author_email' => 'typo3@internetgalerie.ch',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '0.9.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
