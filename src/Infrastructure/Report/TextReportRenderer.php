<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Report;

use Furybee\MigrationRadar\Application\Analysis\AnalysisReport;
use Furybee\MigrationRadar\Application\Analysis\ReportRenderer;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class TextReportRenderer implements ReportRenderer
{
    public function render(AnalysisReport $report): string
    {
        $output = '';
        $byFile = [];
        foreach ($report->issues as $issue) {
            $byFile[$issue->file][] = $issue;
        }

        foreach ($byFile as $file => $issues) {
            $output .= "\n{$file}\n";
            foreach ($issues as $issue) {
                $output .= $this->issue($issue);
            }
        }

        foreach ($report->unparsableFiles as $message) {
            $output .= "\n[not analysed] {$message}\n";
        }

        $output .= sprintf(
            "\n%d migration(s) analysed on %s: %d error(s), %d warning(s), %d info%s\n",
            count($report->files),
            $report->context->platform->describe(),
            $report->countOf(Severity::Error),
            $report->countOf(Severity::Warning),
            $report->countOf(Severity::Info),
            $report->suppressedCount > 0 ? ", {$report->suppressedCount} ignored" : '',
        );

        foreach ($report->hints() as $hint) {
            $output .= "Hint: {$hint}\n";
        }

        return $output;
    }

    private function issue(Issue $issue): string
    {
        return sprintf(
            "  %4d  %-7s %s\n        %s\n        → %s\n",
            $issue->line,
            strtoupper($issue->severity->value),
            $issue->ruleId,
            $issue->message,
            $issue->recommendation,
        );
    }
}
