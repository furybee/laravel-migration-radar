<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Report;

use Furybee\MigrationRadar\Application\Analysis\AnalysisReport;
use Furybee\MigrationRadar\Application\Analysis\ReportRenderer;
use Furybee\MigrationRadar\Domain\Rule\Severity;

/**
 * GitHub Actions workflow commands: issues show up as annotations on the pull request.
 */
final readonly class GithubReportRenderer implements ReportRenderer
{
    public function render(AnalysisReport $report): string
    {
        $output = '';
        foreach ($report->issues as $issue) {
            $output .= sprintf(
                "::%s file=%s,line=%d,title=%s::%s\n",
                match ($issue->severity) {
                    Severity::Error => 'error',
                    Severity::Warning => 'warning',
                    Severity::Info => 'notice',
                },
                self::escapeProperty($issue->file),
                $issue->line,
                self::escapeProperty("migration-radar: {$issue->ruleId}"),
                self::escapeData("{$issue->message}\n{$issue->recommendation}"),
            );
        }

        return $output;
    }

    private static function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    private static function escapeProperty(string $value): string
    {
        return str_replace([':', ','], ['%3A', '%2C'], self::escapeData($value));
    }
}
