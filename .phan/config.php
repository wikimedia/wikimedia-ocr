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
        'SecurityCheck-LikelyFalsePositive',
    ],

    'enable_extended_internal_return_type_plugins' => true,
    'generic_types_enabled' => true,

    'null_casts_as_any_type' => false,
    'scalar_implicit_cast' => false,
    // Note: dead code detection has false positives with symfony magic methods

    'redundant_condition_detection' => true,

    'plugins' => [
        'UnreachableCodePlugin',
        'PregRegexCheckerPlugin',
        'UnusedSuppressionPlugin',
        'DuplicateArrayKeyPlugin',
        'DuplicateExpressionPlugin',
        'RedundantAssignmentPlugin',
        'StrictLiteralComparisonPlugin',
        'DollarDollarPlugin',
        'LoopVariableReusePlugin',
        'StrictComparisonPlugin',
        'SimplifyExpressionPlugin',
        'vendor/drenso/phan-extensions/Plugin/Annotation/SymfonyAnnotationPlugin.php',
        'vendor/mediawiki/phan-taint-check-plugin/GenericSecurityCheckPlugin.php',
    ],
];
