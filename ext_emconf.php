<?php

$EM_CONF['ew_llxml2xliff'] = [
    'title' => 'Converting llxml to xliff',
    'description' => 'Provides a backend module to convert locallang.xml and locallang.php files
    to xliff. For every language, contained in the source file, an extra file gets created.',
    'version' => '2.0.2',
    'state' => 'stable',
    'category' => 'module',
    'author' => 'Sebastian Fischer',
    'author_email' => 'typo3@evoweb.de',
    'author_company' => 'evoWeb',
    'constraints' => [
        'depends' => [
            'php' => '5.5.0-',
            'typo3' => '7.6.0-9.2.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
