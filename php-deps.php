<?php

// Layer rules checked with furybee/php-deps: php path/to/php-deps/analyze.php --check

return [
    'paths' => ['src'],
    'layers' => [
        'Domain' => ['Furybee\MigrationRadar\Domain\**'],
        'Application' => ['Furybee\MigrationRadar\Application\**'],
        'Infrastructure' => ['Furybee\MigrationRadar\Infrastructure\**'],
        'Console' => ['Furybee\MigrationRadar\Console\**'],
    ],
    'rules' => [
        'Domain' => [],
        'Application' => ['Domain'],
        'Infrastructure' => ['Application', 'Domain'],
    ],
    'cycles' => ['level' => 'namespace'],
];
