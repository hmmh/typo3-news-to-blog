<?php

defined('TYPO3_MODE') || die();

call_user_func(function () {
    $fields = [
        'tx_newstoblog' => [
            'exclude' => 1,
            'label' => 'Alte News ID',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'behaviour' => [
                    'allowLanguageSynchronization' => true
                ]
            ]
        ]
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $fields);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'tx_newstoblog');

    $GLOBALS['TCA']['pages']['columns']['categories']['config']['behaviour']['allowLanguageSynchronization'] = true;
});
