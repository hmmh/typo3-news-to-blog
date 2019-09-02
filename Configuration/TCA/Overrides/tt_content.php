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
            ]
        ],
        'tx_newstoblog_ce' => [
            'exclude' => 1,
            'label' => 'Alte News CE-ID',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
            ]
        ]
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $fields);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_newstoblog,tx_newstoblog_ce');
});
