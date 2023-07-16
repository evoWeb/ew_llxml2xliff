<?php

defined('TYPO3') or die();

use Evoweb\EwLlxml2xliff\Controller\FileController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(function () {
    ExtensionUtility::registerModule(
        'EwLlxml2xliff',
        'tools',
        'llxmlconverter',
        'after:extensionmanager',
        [
            FileController::class => 'index, showFiles, convertFile, confirmConversion',
        ],
        [
            'access' => 'user,group',
            'name' => 'tools_llxmlconverter',
            'iconIdentifier' => 'llxml2xlifficon',
            'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf',
        ]
    );
});
