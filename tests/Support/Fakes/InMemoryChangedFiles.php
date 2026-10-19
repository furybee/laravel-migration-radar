<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support\Fakes;

use Furybee\MigrationRadar\Application\Port\ChangedFiles;

final readonly class InMemoryChangedFiles implements ChangedFiles
{
    /**
     * @param list<string> $files
     */
    public function __construct(private array $files = [])
    {
    }

    public function since(string $reference): array
    {
        return $this->files;
    }
}
