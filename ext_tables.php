<?php

defined('TYPO3_MODE') || die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'EwLlxml2xliff',
    'tools',
    'llxmlconverter',
    'after:extensionmanager',
    [
        \Evoweb\EwLlxml2xliff\Controller\FileController::class => 'index, showFiles, convertFile, confirmConversion',
    ],
    [
        'access' => 'user,group',
        'icon' => 'EXT:ew_llxml2xliff/Resources/Public/Icons/Extension.svg',
        'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf',
    ]
);
