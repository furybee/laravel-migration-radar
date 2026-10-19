<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return (string) preg_replace('#/+#', '/', $path);
    }
}
