<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure;

final class ProjectPath
{
    public static function absolute(string $projectDirectory, string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($projectDirectory, '/') . '/' . $path;
    }

    /**
     * Paths outside the project stay absolute.
     */
    public static function relative(string $projectDirectory, string $path): string
    {
        $prefix = rtrim($projectDirectory, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
