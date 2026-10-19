<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support\Fakes;

use Furybee\MigrationRadar\Application\Port\MigrationFinder;

final readonly class InMemoryMigrationFinder implements MigrationFinder
{
    /**
     * @param list<string> $files
     */
    public function __construct(private array $files)
    {
    }

    public function find(array $paths): array
    {
        return array_values(array_filter(
            $this->files,
            static fn(string $file) => array_filter($paths, static fn(string $path) => str_starts_with($file, $path)) !== [],
        ));
    }
}
