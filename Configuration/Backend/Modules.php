<?php

use Evoweb\EwLlxml2xliff\Controller\FileController;

return [
    'web_EwLlxml2xliff' => [
        'parent' => 'admin',
        'access' => 'admin',
        'path' => '/module/web/llxmlconverter',
        'iconIdentifier' => 'llxml2xlifficon',
        'labels' => 'ew_llxml2xliff.module',
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
        'moduleData' => [
            'extension' => '',
            'file' => '',
        ],
    ],
];
