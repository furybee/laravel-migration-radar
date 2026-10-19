<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Report;

use Furybee\MigrationRadar\Application\Analysis\AnalysisReport;
use Furybee\MigrationRadar\Application\Analysis\ReportRenderer;
use Furybee\MigrationRadar\Domain\Rule\Issue;

final readonly class JsonReportRenderer implements ReportRenderer
{
    public function render(AnalysisReport $report): string
    {
        return json_encode([
            'platform' => $report->context->platform->describe(),
            'files' => $report->files,
            'issues' => array_map(static fn(Issue $issue) => [
                'rule' => $issue->ruleId,
                'severity' => $issue->severity->value,
                'file' => $issue->file,
                'line' => $issue->line,
                'message' => $issue->message,
                'recommendation' => $issue->recommendation,
            ], $report->issues),
            'ignored' => $report->suppressedCount,
            'unparsable' => $report->unparsableFiles,
            'hints' => $report->hints(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
