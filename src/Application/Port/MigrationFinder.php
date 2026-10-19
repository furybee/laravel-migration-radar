<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Port;

interface MigrationFinder
{
    /**
     * @param list<string> $paths files or directories
     * @return list<string> migration files, sorted
     */
    public function find(array $paths): array;
}
