<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Application\Port\ChangedFiles;
use Furybee\MigrationRadar\Application\Port\MigrationFinder;

final readonly class SelectMigrations
{
    public function __construct(
        private MigrationFinder $finder,
        private ChangedFiles $changedFiles,
    ) {
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    public function all(array $paths): array
    {
        return array_map(PathNormalizer::normalize(...), $this->finder->find($paths));
    }

    /**
     * Only the migrations added or modified since $reference: the ones that have not run in production yet.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    public function changedSince(array $paths, string $reference): array
    {
        $changed = array_flip(array_map(PathNormalizer::normalize(...), $this->changedFiles->since($reference)));

        return array_values(array_filter($this->all($paths), static fn(string $path) => isset($changed[$path])));
    }
}
