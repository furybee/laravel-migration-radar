<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class AnalysisReport
{
    /**
     * @param list<Issue> $issues sorted by file then line
     * @param list<string> $files analysed migrations
     * @param list<string> $unparsableFiles one message per migration that could not be read
     */
    public function __construct(
        public array $issues,
        public array $files,
        public DatabaseContext $context,
        public int $suppressedCount = 0,
        public array $unparsableFiles = [],
    ) {
    }

    public function countOf(Severity $severity): int
    {
        return count(array_filter($this->issues, static fn(Issue $issue) => $issue->severity === $severity));
    }

    public function hasIssuesAtLeast(Severity $threshold): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity->isAtLeast($threshold)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function hints(): array
    {
        $hints = [];
        if (!$this->context->platform->isKnown()) {
            $hints[] = 'Database platform unknown: set "platform" (e.g. mysql:8.0.35) or pass --dsn for accurate, version aware results.';
        }
        if ($this->context->tableRows === [] && $this->context->assumeLargeWhenUnknown) {
            $hints[] = 'No table statistics: every existing table is assumed large. Pass --dsn or set "table_rows" to only flag large tables.';
        }

        return $hints;
    }
}
