<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Port;

final class MigrationNotParsable extends \RuntimeException
{
    public static function at(string $path, string $reason): self
    {
        return new self("{$path}: {$reason}");
    }
}
