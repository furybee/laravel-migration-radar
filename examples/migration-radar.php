<?php

// Copy to the root of your project as migration-radar.php. Every key is optional.

return [
    // Where the migrations live
    'paths' => ['database/migrations'],

    // Target database. Without it (or --dsn), version dependent rules assume the worst.
    'platform' => 'mysql:8.0.35', // mariadb:10.11, pgsql:16...

    // Read the version and the table sizes from a database: production replica, or a recent copy.
    // Better given through the MIGRATION_RADAR_DSN environment variable than written here.
    // 'dsn' => 'mysql://readonly:secret@replica.internal:3306/app',

    // A table is large from this many rows on: lock and rebuild rules only report large tables
    'large_table_rows' => 1_000_000,

    // Without statistics, a table is presumed large (true) or small (false)
    'assume_large_tables' => true,

    // Row estimates, when the database cannot be reached from CI. They win over the database statistics.
    'table_rows' => [
        'users' => 4_000_000,
        'audit_logs' => 120_000_000,
    ],

    // Rules turned off, see `vendor/bin/migration-radar rules`
    'disabled_rules' => [],

    // Rules ignored for whole files, by path glob: typically migrations that already ran everywhere
    'ignore' => [
        'database/migrations/2019_*' => ['*'],
    ],
];
