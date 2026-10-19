<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Integration\Console;

use Furybee\MigrationRadar\Console\MigrationRadarApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AnalyseCommandTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../../Fixtures/project';

    public function testFailsOnErrorsAndOnlyFlagsLargeTables(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('add-foreign-key-locks-table', $output);
        $this->assertStringContainsString('"users" (~5,000,000 rows)', $output);
        $this->assertStringNotContainsString('"settings"', $output);
        $this->assertStringContainsString('3 migration(s) analysed on MySQL 8.0.35: 1 error(s), 1 warning(s), 0 info, 1 ignored', $output);
    }

    public function testFailsOnWarningsOnlyWhenAsked(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['paths' => ['database/migrations/2026_01_02_000000_drop_users_legacy_column.php']]));
        $this->assertSame(Command::FAILURE, $tester->execute(['paths' => ['database/migrations/2026_01_02_000000_drop_users_legacy_column.php'], '--fail-on' => 'warning']));
        $this->assertSame(Command::SUCCESS, $tester->execute(['--fail-on' => 'never']));
    }

    public function testLetsCommandLineOptionsOverrideTheConfiguration(): void
    {
        $tester = $this->tester();

        $tester->execute(['--platform' => 'pgsql:16', '--format' => 'json']);

        $report = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($report);
        $this->assertSame('PostgreSQL 16', $report['platform']);
        $this->assertStringContainsString('NOT VALID', (string) json_encode($report['issues']));
    }

    public function testReportsInvalidInputWithoutAStackTrace(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::INVALID, $tester->execute(['--platform' => 'oracle']));
        $this->assertStringContainsString('Unknown database platform "oracle"', $tester->getDisplay());
        $this->assertSame(Command::INVALID, $tester->execute(['--format' => 'xml']));
        $this->assertSame(Command::INVALID, $tester->execute(['--fail-on' => 'sometimes']));
        $this->assertSame(Command::INVALID, $tester->execute(['--config' => 'missing.php']));
        $this->assertSame(Command::INVALID, $tester->execute(['--dsn' => 'mysql://127.0.0.1:1/nothing']));
    }

    public function testReportsTheVersionComposerInstalled(): void
    {
        $version = (new MigrationRadarApplication(self::PROJECT))->getVersion();

        $this->assertNotSame('unknown', $version);
        $this->assertNotSame('0.1.0', $version);
    }

    public function testListsTheRules(): void
    {
        $tester = new CommandTester((new MigrationRadarApplication(self::PROJECT))->find('rules'));

        $tester->execute([]);

        $this->assertStringContainsString('rename-column', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new MigrationRadarApplication((string) realpath(self::PROJECT)))->find('analyse'));
    }
}
