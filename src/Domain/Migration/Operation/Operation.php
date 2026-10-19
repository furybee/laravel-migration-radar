<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

/**
 * A single change a migration applies to the database.
 */
interface Operation
{
    /**
     * Table the operation touches, null when it cannot be determined (raw SQL).
     */
    public function table(): ?string;

    public function line(): int;
}
