<?php

defined('TYPO3_MODE') || die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \HMMH\NewsToBlog\Hook\DataHandler::class;

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
    ->registerImplementation(\GeorgRinger\News\Domain\Model\TtContent::class, \HMMH\NewsToBlog\Domain\Model\TtContent::class);
