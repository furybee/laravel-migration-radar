<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support\Fakes;

use Furybee\MigrationRadar\Application\Port\DatabaseInspector;
use Furybee\MigrationRadar\Domain\Database\Platform;

final readonly class InMemoryDatabaseInspector implements DatabaseInspector
{
    /**
     * @param array<string, int> $tableRows
     */
    public function __construct(
        private Platform $platform,
        private array $tableRows = [],
    ) {
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function tableRows(): array
    {
        return $this->tableRows;
    }
}
