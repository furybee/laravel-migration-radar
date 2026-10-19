<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Database;

use Furybee\MigrationRadar\Application\Port\DatabaseInspector;
use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Without a connection nothing is known: the configuration fills the gaps, or rules assume the worst.
 */
final readonly class NoDatabaseInspector implements DatabaseInspector
{
    public function platform(): Platform
    {
        return Platform::unknown();
    }

    public function tableRows(): array
    {
        return [];
    }
}
