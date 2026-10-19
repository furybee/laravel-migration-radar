<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Port;

use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Source of facts about the target database: a live connection, or nothing at all.
 */
interface DatabaseInspector
{
    /**
     * @throws DatabaseUnreachable
     */
    public function platform(): Platform;

    /**
     * @return array<string, int> estimated rows per table
     * @throws DatabaseUnreachable
     */
    public function tableRows(): array;
}
