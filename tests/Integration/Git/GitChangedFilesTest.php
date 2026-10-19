<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Integration\Git;

use Furybee\MigrationRadar\Application\Port\ChangedFilesUnavailable;
use Furybee\MigrationRadar\Infrastructure\Git\GitChangedFiles;
use Furybee\MigrationRadar\Infrastructure\Git\Process;
use Furybee\MigrationRadar\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class GitChangedFilesTest extends TestCase
{
    private TemporaryDirectory $repository;

    protected function setUp(): void
    {
        if ((new Process())->run(['git', '--version'], sys_get_temp_dir())[0] !== 0) {
            $this->markTestSkipped('git is not installed');
        }

        $this->repository = TemporaryDirectory::create();
        $this->git('init', '--quiet', '--initial-branch=main');
        $this->repository->write('database/migrations/2026_01_01_old.php', '<?php');
        $this->repository->write('database/migrations/2026_01_02_edited.php', '<?php');
        $this->commit('initial');
        $this->git('checkout', '--quiet', '-b', 'feature');
    }

    protected function tearDown(): void
    {
        if (isset($this->repository)) {
            $this->repository->delete();
        }
    }

    public function testListsCommittedUncommittedAndUntrackedChangesOfTheBranch(): void
    {
        $this->repository->write('database/migrations/2026_02_01_committed.php', '<?php');
        $this->commit('add migration');
        $this->repository->write('database/migrations/2026_01_02_edited.php', '<?php // edited');
        $this->repository->write('database/migrations/2026_02_02_untracked.php', '<?php');

        $files = (new GitChangedFiles($this->repository->path))->since('main');
        sort($files);

        $this->assertSame([
            'database/migrations/2026_01_02_edited.php',
            'database/migrations/2026_02_01_committed.php',
            'database/migrations/2026_02_02_untracked.php',
        ], $files);
    }

    public function testListsPathsRelativeToASubdirectory(): void
    {
        $this->repository->write('back/database/migrations/2026_02_01_new.php', '<?php');

        $files = (new GitChangedFiles($this->repository->path . '/back'))->since('main');

        $this->assertSame(['database/migrations/2026_02_01_new.php'], $files);
    }

    public function testRejectsUnknownReferences(): void
    {
        $this->expectException(ChangedFilesUnavailable::class);

        (new GitChangedFiles($this->repository->path))->since('does-not-exist');
    }

    public function testRejectsReferencesLookingLikeOptions(): void
    {
        $this->expectException(ChangedFilesUnavailable::class);

        (new GitChangedFiles($this->repository->path))->since('--output=/tmp/x');
    }

    private function commit(string $message): void
    {
        $this->git('add', '--all');
        $this->git('-c', 'user.email=radar@example.com', '-c', 'user.name=Radar', 'commit', '--quiet', '-m', $message);
    }

    private function git(string ...$arguments): void
    {
        [$exitCode, , $stderr] = (new Process())->run(['git', ...array_values($arguments)], $this->repository->path);
        $this->assertSame(0, $exitCode, $stderr);
    }
}
