<?php

declare(strict_types = 1);

return [
    'target_php_version' => null,

    'directory_list' => [
        'src',
        'vendor',
    ],

    'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',

    'exclude_analysis_directory_list' => [
        'vendor/',
    ],

    'suppress_issue_types' => [
        'PhanUnreferencedUseNormal', // PHPCS does this already and without false positives.
    ],

    'plugins' => [
        'vendor/drenso/phan-extensions/Plugin/Annotation/SymfonyAnnotationPlugin.php',
        'vendor/mediawiki/phan-taint-check-plugin/GenericSecurityCheckPlugin.php'
    ],
];
