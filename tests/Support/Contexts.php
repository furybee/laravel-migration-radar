<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;

/**
 * Database contexts for rule tests: `users` is large, `settings` is small.
 */
final class Contexts
{
    public const LARGE_TABLE = 'users';
    public const SMALL_TABLE = 'settings';

    public static function on(string $platform): DatabaseContext
    {
        return new DatabaseContext(
            $platform === 'unknown' ? Platform::unknown() : Platform::fromString($platform),
            [self::LARGE_TABLE => 5_000_000, self::SMALL_TABLE => 10],
            1_000_000,
            assumeLargeWhenUnknown: true,
        );
    }
}
