<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Report;

use Furybee\MigrationRadar\Application\Analysis\AnalysisReport;
use Furybee\MigrationRadar\Application\Analysis\ReportRenderer;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * GitLab Code Quality report, to declare as `artifacts:reports:codequality`.
 */
final readonly class GitlabReportRenderer implements ReportRenderer
{
    public function render(AnalysisReport $report): string
    {
        return json_encode(array_map(static fn(Issue $issue) => [
            'description' => "{$issue->message} {$issue->recommendation}",
            'check_name' => "migration-radar.{$issue->ruleId}",
            'fingerprint' => md5("{$issue->ruleId}|{$issue->file}|{$issue->message}"),
            'severity' => match ($issue->severity) {
                Severity::Error => 'critical',
                Severity::Warning => 'major',
                Severity::Info => 'info',
            },
            'location' => ['path' => $issue->file, 'lines' => ['begin' => $issue->line]],
        ], $report->issues), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
