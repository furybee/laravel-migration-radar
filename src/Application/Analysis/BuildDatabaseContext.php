<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Application\Configuration\Configuration;
use Furybee\MigrationRadar\Application\Port\DatabaseInspector;
use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Merges what the database reports with the configuration, which always wins: it is the deliberate input.
 */
final readonly class BuildDatabaseContext
{
    public function __construct(private DatabaseInspector $inspector)
    {
    }

    public function build(Configuration $configuration): DatabaseContext
    {
        $platform = $configuration->platform === null
            ? $this->inspector->platform()
            : Platform::fromString($configuration->platform);

        return new DatabaseContext(
            $platform,
            $configuration->tableRows + $this->inspector->tableRows(),
            $configuration->largeTableRows,
            $configuration->assumeLargeTables,
        );
    }
}
