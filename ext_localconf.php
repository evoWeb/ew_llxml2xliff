<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(function () {
    ExtensionManagementUtility::addTypoScriptSetup(
        '@import \'EXT:ew_llxml2xliff/Configuration/TypoScript/modules.typoscript\''
    );
});
