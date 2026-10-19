<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support;

use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Severity;
use PHPUnit\Framework\Assert;

final class IssueAssertions
{
    /**
     * @param list<Issue> $issues
     */
    public static function assertSingleIssue(array $issues, Severity $severity, int $line): Issue
    {
        Assert::assertCount(1, $issues);
        Assert::assertSame($severity, $issues[0]->severity);
        Assert::assertSame($line, $issues[0]->line);
        Assert::assertSame(MigrationBuilder::PATH, $issues[0]->file);

        return $issues[0];
    }
}
