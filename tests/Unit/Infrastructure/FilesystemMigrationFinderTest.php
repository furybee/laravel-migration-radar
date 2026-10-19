<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure;

use Furybee\MigrationRadar\Infrastructure\FilesystemMigrationFinder;
use Furybee\MigrationRadar\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class FilesystemMigrationFinderTest extends TestCase
{
    public function testFindsPhpFilesRelativeToTheProject(): void
    {
        $project = TemporaryDirectory::create();
        $project->write('database/migrations/2026_01_02_b.php', '<?php');
        $project->write('database/migrations/2026_01_01_a.php', '<?php');
        $project->write('database/migrations/README.md', '');
        $project->write('modules/billing/migrations/2026_01_03_c.php', '<?php');

        $files = (new FilesystemMigrationFinder($project->path))->find([
            'database/migrations',
            $project->path . '/modules/billing/migrations/2026_01_03_c.php',
            'missing',
        ]);
        $project->delete();

        $this->assertSame([
            'database/migrations/2026_01_01_a.php',
            'database/migrations/2026_01_02_b.php',
            'modules/billing/migrations/2026_01_03_c.php',
        ], $files);
    }
}
