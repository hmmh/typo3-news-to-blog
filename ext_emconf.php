<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'hmmh News to blog',
    'description' => 'Includes TypoScript and HTML templates for a project',
    'category' => 'misc',
    'author' => 'hmmh Team TYPO3',
    'author_email' => 'typo3@hmmh.de',
    'author_company' => '',
    'shy' => '',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'module' => '',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
            'news' => '>=7.2',
            'blog' => '>=9.1'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
];
