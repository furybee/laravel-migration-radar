<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

/**
 * Output port of the analysis: turns its report into a format for people or CI tools.
 */
interface ReportRenderer
{
    public function render(AnalysisReport $report): string;
}
