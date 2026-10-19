<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * Schema changes are not transactional on MySQL: a failing data step leaves a half-migrated database,
 * and a long backfill holds the locks taken by the schema change.
 */
final readonly class MixedSchemaAndDataRule implements Rule
{
    public function id(): string
    {
        return 'mixed-schema-and-data';
    }

    public function description(): string
    {
        return 'Changing the schema and the data in the same migration';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $dataChanges = $migration->dataChanges();
        if ($dataChanges === [] || !$migration->changesSchema()) {
            return [];
        }

        $first = $dataChanges[0];

        return [new Issue(
            $this->id(),
            Severity::Warning,
            "This migration changes the schema and also the data ({$first->description}).",
            'Move the data change to its own migration or command, batched (chunkById), '
                . 'so a failure does not leave the schema half migrated and locks are not held during the backfill.',
            $migration->path,
            $first->line,
        )];
    }
}
