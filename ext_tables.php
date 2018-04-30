<?php
defined('TYPO3_MODE') || die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Evoweb.EwLlxml2xliff',
    'tools',
    'llxmlconverter',
    'after:lang',
    [
        'File' => 'index, showFiles, convertFile, confirmConversion',
    ],
    [
        'access' => 'user,group',
        'icon' => 'EXT:lang/Resources/Public/Icons/module-lang.svg',
        'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf',
    ]
);
