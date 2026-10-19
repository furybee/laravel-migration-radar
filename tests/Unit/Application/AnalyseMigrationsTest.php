<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Unit\Application;

use Furybee\MigrationRadar\Application\Analysis\AnalyseMigrations;
use Furybee\MigrationRadar\Application\Analysis\PathSuppressions;
use Furybee\MigrationRadar\Application\Analysis\RuleSet;
use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Database\Platform;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\AddForeignKey;
use Furybee\MigrationRadar\Domain\Migration\Operation\CreateTable;
use Furybee\MigrationRadar\Domain\Migration\Operation\DropColumn;
use Furybee\MigrationRadar\Domain\Migration\Operation\RenameColumn;
use Furybee\MigrationRadar\Domain\Migration\Suppression;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use Furybee\MigrationRadar\Tests\Support\Fakes\InMemoryMigrationParser;
use PHPUnit\Framework\TestCase;

final class AnalyseMigrationsTest extends TestCase
{
    private const FIRST = 'database/migrations/2026_01_01_a.php';
    private const SECOND = 'database/migrations/2026_01_02_b.php';

    public function testRunsEveryRuleAndSortsIssuesByFileAndLine(): void
    {
        $parser = (new InMemoryMigrationParser())
            ->add(new Migration(self::SECOND, [new DropColumn('users', 'legacy', 3)]))
            ->add(new Migration(self::FIRST, [new RenameColumn('users', 'name', 'label', 9), new DropColumn('users', 'old', 4)]));

        $report = (new AnalyseMigrations($parser, RuleSet::default()))->analyse([self::SECOND, self::FIRST], $this->context());

        $this->assertSame(
            [[self::FIRST, 4, 'drop-column'], [self::FIRST, 9, 'rename-column'], [self::SECOND, 3, 'drop-column']],
            array_map(static fn($issue) => [$issue->file, $issue->line, $issue->ruleId], $report->issues),
        );
        $this->assertSame([self::SECOND, self::FIRST], $report->files);
        $this->assertTrue($report->hasIssuesAtLeast(Severity::Error));
    }

    public function testCountsIssuesIgnoredByCommentsOrConfiguration(): void
    {
        $parser = (new InMemoryMigrationParser())
            ->add(new Migration(self::FIRST, [new DropColumn('users', 'old', 4)], [new Suppression(3, 4, ['drop-column'])]))
            ->add(new Migration(self::SECOND, [new RenameColumn('users', 'name', 'label', 9)]));

        $analyse = new AnalyseMigrations($parser, RuleSet::default(), new PathSuppressions(['database/migrations/2026_01_02_*' => ['*']]));
        $report = $analyse->analyse([self::FIRST, self::SECOND], $this->context());

        $this->assertSame([], $report->issues);
        $this->assertSame(2, $report->suppressedCount);
    }

    public function testReportsMigrationsItCannotParseAndKeepsGoing(): void
    {
        $parser = (new InMemoryMigrationParser())->add(new Migration(self::SECOND, [new DropColumn('users', 'old', 4)]));

        $report = (new AnalyseMigrations($parser, RuleSet::default()))->analyse([self::FIRST, self::SECOND], $this->context());

        $this->assertSame([self::FIRST . ': syntax error'], $report->unparsableFiles);
        $this->assertCount(1, $report->issues);
    }

    public function testTreatsTablesCreatedEarlierInTheBranchAsNew(): void
    {
        $parser = (new InMemoryMigrationParser())
            ->add(new Migration(self::FIRST, [new CreateTable('invoices', 3)]))
            ->add(new Migration(self::SECOND, [
                new AddForeignKey('invoices', ['user_id'], 'users', 4),
                new AddForeignKey('orders', ['user_id'], 'users', 5),
            ]));
        $analyse = new AnalyseMigrations($parser, RuleSet::default());

        $unreleased = $analyse->analyseUnreleased([self::FIRST, self::SECOND], $this->context());
        $released = $analyse->analyse([self::FIRST, self::SECOND], $this->context());

        $this->assertSame([5], array_map(static fn($issue) => $issue->line, $unreleased->issues));
        $this->assertSame([4, 5], array_map(static fn($issue) => $issue->line, $released->issues));
    }

    private function context(): DatabaseContext
    {
        return new DatabaseContext(Platform::fromString('mysql:8.0.35'));
    }
}
