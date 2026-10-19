<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;

interface Rule
{
    /**
     * Stable kebab-case identifier, used in configuration and ignore comments.
     */
    public function id(): string;

    public function description(): string;

    /**
     * @return list<Issue>
     */
    public function check(Migration $migration, DatabaseContext $context): array;
}
