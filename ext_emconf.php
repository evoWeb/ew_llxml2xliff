<?php

$EM_CONF['ew_llxml2xliff'] = [
    'title' => 'Converting llxml to xliff',
    'description' => 'Based on work of Xavier Perseguers for the obsolete extdeveval',
    'version' => '2.0.0',
    'state' => 'stable',
    'category' => 'module',
    'author' => 'Sebastian Fischer',
    'author_email' => 'typo3@evoweb.de',
    'author_company' => 'evoWeb',
    'constraints' => [
        'depends' => [
            'php' => '5.5.0-',
            'typo3' => '7.6.0-8.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
