<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Infrastructure\Report;

use Furybee\MigrationRadar\Application\Analysis\AnalysisReport;
use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Infrastructure\Report\GithubReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\GitlabReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\JsonReportRenderer;
use Furybee\MigrationRadar\Infrastructure\Report\TextReportRenderer;
use PHPUnit\Framework\TestCase;

final class ReportRenderersTest extends TestCase
{
    private const FILE = 'database/migrations/2026_01_01_rename.php';

    public function testTextGroupsIssuesByFileAndSummarises(): void
    {
        $output = (new TextReportRenderer())->render($this->report(Platform::fromString('mysql:8.0.35')));

        $this->assertStringContainsString(self::FILE . "\n", $output);
        $this->assertStringContainsString('ERROR   rename-column', $output);
        $this->assertStringContainsString('→ Add the new column', $output);
        $this->assertStringContainsString('[not analysed] broken.php: syntax error', $output);
        $this->assertStringContainsString('2 migration(s) analysed on MySQL 8.0.35: 1 error(s), 1 warning(s), 0 info, 3 ignored', $output);
        $this->assertStringNotContainsString('Hint: Database platform unknown', $output);
    }

    public function testTextHintsAtMissingDatabaseFacts(): void
    {
        $output = (new TextReportRenderer())->render($this->report(Platform::unknown(), tableRows: []));

        $this->assertStringContainsString('Hint: Database platform unknown', $output);
        $this->assertStringContainsString('Hint: No table statistics', $output);
    }

    public function testJsonExposesEveryField(): void
    {
        /** @var array{platform: string, ignored: int, issues: list<array<string, mixed>>} $json */
        $json = json_decode((new JsonReportRenderer())->render($this->report(Platform::fromString('pgsql:16'))), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('PostgreSQL 16', $json['platform']);
        $this->assertSame(3, $json['ignored']);
        $this->assertSame(
            ['rule' => 'rename-column', 'severity' => 'error', 'file' => self::FILE, 'line' => 12, 'message' => 'Renaming "a", "b": breaks', 'recommendation' => 'Add the new column.'],
            $json['issues'][0],
        );
    }

    public function testGithubEmitsEscapedAnnotations(): void
    {
        $output = (new GithubReportRenderer())->render($this->report(Platform::unknown()));

        $this->assertSame(
            "::error file=" . self::FILE . ",line=12,title=migration-radar%3A rename-column::Renaming \"a\", \"b\": breaks%0AAdd the new column.\n"
            . "::warning file=" . self::FILE . ",line=20,title=migration-radar%3A drop-column::Dropping 100%25 of it%0AShip the code first.\n",
            $output,
        );
    }

    public function testGitlabEmitsACodeQualityReport(): void
    {
        /** @var list<array{severity: string, check_name: string, fingerprint: string, location: array<string, mixed>}> $report */
        $report = json_decode((new GitlabReportRenderer())->render($this->report(Platform::unknown())), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('critical', $report[0]['severity']);
        $this->assertSame('migration-radar.rename-column', $report[0]['check_name']);
        $this->assertSame(['path' => self::FILE, 'lines' => ['begin' => 12]], $report[0]['location']);
        $this->assertSame('major', $report[1]['severity']);
        $this->assertNotSame($report[0]['fingerprint'], $report[1]['fingerprint']);
    }

    /**
     * @param array<string, int> $tableRows
     */
    private function report(Platform $platform, array $tableRows = ['users' => 10]): AnalysisReport
    {
        return new AnalysisReport(
            [
                new Issue('rename-column', Severity::Error, 'Renaming "a", "b": breaks', 'Add the new column.', self::FILE, 12),
                new Issue('drop-column', Severity::Warning, 'Dropping 100% of it', 'Ship the code first.', self::FILE, 20),
            ],
            [self::FILE, 'broken.php'],
            new DatabaseContext($platform, $tableRows),
            suppressedCount: 3,
            unparsableFiles: ['broken.php: syntax error'],
        );
    }
}
