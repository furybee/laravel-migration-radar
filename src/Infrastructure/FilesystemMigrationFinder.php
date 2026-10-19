<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure;

use Furybee\MigrationRadar\Application\Port\MigrationFinder;

/**
 * Paths in and out are relative to the project directory, which is how reports must show them.
 */
final readonly class FilesystemMigrationFinder implements MigrationFinder
{
    public function __construct(private string $projectDirectory)
    {
    }

    public function find(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $absolute = ProjectPath::absolute($this->projectDirectory, $path);
            if (is_file($absolute)) {
                $files[] = ProjectPath::relative($this->projectDirectory, $absolute);
                continue;
            }

            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = ProjectPath::relative($this->projectDirectory, $file->getPathname());
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
