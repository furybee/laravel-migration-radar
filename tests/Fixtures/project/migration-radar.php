<?php

return [
    'platform' => 'mysql:8.0.35',
    'table_rows' => ['users' => 5_000_000, 'settings' => 12],
    'ignore' => ['database/migrations/2020_*' => ['*']],
];
