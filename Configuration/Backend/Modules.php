<?php

use Evoweb\EwLlxml2xliff\Controller\FileController;

return [
    'web_EwLlxml2xliff' => [
        'parent' => 'tools',
        'access' => 'user,group',
        'path' => '/module/web/llxmlconverter',
        'iconIdentifier' => 'llxml2xlifficon',
        'inheritNavigationComponentFromMainModule' => false,
        'labels' => 'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => FileController::class . '::indexAction',
            ],
            'showFiles' => [
                'target' => FileController::class . '::showFilesAction',
                'methods' => ['POST'],
            ],
            'confirmConversion' => [
                'target' => FileController::class . '::confirmConversionAction',
                'methods' => ['POST'],
            ],
            'convertFile' => [
                'target' => FileController::class . '::convertFileAction',
                'methods' => ['POST'],
            ]
        ],
    ],
];
