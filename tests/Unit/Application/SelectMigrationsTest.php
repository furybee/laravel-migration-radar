<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Application;

use Furybee\MigrationRadar\Application\Analysis\SelectMigrations;
use Furybee\MigrationRadar\Tests\Support\Fakes\InMemoryChangedFiles;
use Furybee\MigrationRadar\Tests\Support\Fakes\InMemoryMigrationFinder;
use PHPUnit\Framework\TestCase;

final class SelectMigrationsTest extends TestCase
{
    private const MIGRATIONS = ['database/migrations/a.php', 'database/migrations/b.php', 'modules/billing/migrations/c.php'];

    public function testSelectsEveryMigrationOfThePaths(): void
    {
        $selection = new SelectMigrations(new InMemoryMigrationFinder(self::MIGRATIONS), new InMemoryChangedFiles());

        $this->assertSame(['database/migrations/a.php', 'database/migrations/b.php'], $selection->all(['database/migrations']));
    }

    public function testKeepsOnlyChangedMigrationsWhateverThePathNotation(): void
    {
        $changed = new InMemoryChangedFiles(['./database/migrations/b.php', 'app/Models/User.php', 'modules/billing/migrations/c.php']);
        $selection = new SelectMigrations(new InMemoryMigrationFinder(self::MIGRATIONS), $changed);

        $this->assertSame(['database/migrations/b.php'], $selection->changedSince(['database/migrations'], 'origin/main'));
    }
}
