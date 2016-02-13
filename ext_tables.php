<?php
defined('TYPO3_MODE') or die();

// Avoid that this block is loaded in frontend or within upgrade wizards
if (TYPO3_MODE === 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Evoweb.EwLlxml2xliff',
        'tools',
        'llxmlconverter',
        'after:lang',
        array(
            'File' => 'index, showFiles, convertFile, confirmConversion'
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:lang/Resources/Public/Icons/module-lang.svg',
            'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf'
        )
    );
}
