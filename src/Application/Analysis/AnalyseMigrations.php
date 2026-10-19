<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Analysis;

use Furybee\MigrationRadar\Application\Port\MigrationNotParsable;
use Furybee\MigrationRadar\Application\Port\MigrationParser;
use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Rule\Issue;

final readonly class AnalyseMigrations
{
    public function __construct(
        private MigrationParser $parser,
        private RuleSet $rules,
        private PathSuppressions $pathSuppressions = new PathSuppressions(),
    ) {
    }

    /**
     * Migrations analysed one by one, as if each was the next to run in production.
     *
     * @param list<string> $files
     */
    public function analyse(array $files, DatabaseContext $context): AnalysisReport
    {
        return $this->run($files, $context, carriesNewTables: false);
    }

    /**
     * Migrations that have not run in production yet, in execution order: a table one of them creates
     * is new, so empty and unused, for the ones after it.
     *
     * @param list<string> $files
     */
    public function analyseUnreleased(array $files, DatabaseContext $context): AnalysisReport
    {
        return $this->run($files, $context, carriesNewTables: true);
    }

    /**
     * @param list<string> $files
     */
    private function run(array $files, DatabaseContext $context, bool $carriesNewTables): AnalysisReport
    {
        $issues = [];
        $unparsable = [];
        $suppressed = 0;

        foreach ($files as $file) {
            try {
                $migration = $this->parser->parse($file);
            } catch (MigrationNotParsable $e) {
                $unparsable[] = $e->getMessage();
                continue;
            }

            $migrationContext = $context;
            if ($carriesNewTables) {
                $context = $context->withNewTables($migration->newTablesAfter($context->newTables));
            }

            foreach ($this->issuesOf($migration, $migrationContext) as $issue) {
                if ($this->isSuppressed($migration, $issue)) {
                    $suppressed++;
                    continue;
                }
                $issues[] = $issue;
            }
        }

        usort($issues, static fn(Issue $a, Issue $b) => [$a->file, $a->line, $a->ruleId] <=> [$b->file, $b->line, $b->ruleId]);

        return new AnalysisReport($issues, $files, $context, $suppressed, $unparsable);
    }

    /**
     * @return list<Issue>
     */
    private function issuesOf(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($this->rules->rules as $rule) {
            array_push($issues, ...$rule->check($migration, $context));
        }

        return $issues;
    }

    private function isSuppressed(Migration $migration, Issue $issue): bool
    {
        return $migration->isSuppressed($issue->ruleId, $issue->line)
            || $this->pathSuppressions->covers($migration->path, $issue->ruleId);
    }
}
