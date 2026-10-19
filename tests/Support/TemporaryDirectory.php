<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support;

final readonly class TemporaryDirectory
{
    private function __construct(public string $path)
    {
    }

    public static function create(): self
    {
        $path = sys_get_temp_dir() . '/migration-radar-' . bin2hex(random_bytes(6));
        mkdir($path, 0777, true);

        return new self((string) realpath($path));
    }

    public function write(string $relativePath, string $content): string
    {
        $file = "{$this->path}/{$relativePath}";
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);

        return $file;
    }

    public function delete(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->path);
    }
}
