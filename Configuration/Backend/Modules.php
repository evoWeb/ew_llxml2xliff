<?php

use Evoweb\EwLlxml2xliff\Controller\FileController;

return [
    'web_EwLlxml2xliff' => [
        'parent' => 'tools',
        'access' => 'user,group',
        'path' => '/module/web/llxmlconverter',
        'iconIdentifier' => 'llxml2xlifficon',
        'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => FileController::class . '::selectExtensionAction',
            ],
            'showFiles' => [
                'target' => FileController::class . '::selectFileAction',
            ],
            'confirmConversion' => [
                'target' => FileController::class . '::confirmConversionAction',
            ],
            'convertFile' => [
                'target' => FileController::class . '::convertFileAction',
            ],
        ],
    ],
];
