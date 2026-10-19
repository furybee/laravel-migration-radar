<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule\Rules;

use Furybee\MigrationRadar\Domain\Database\DatabaseContext;
use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\RawStatement;
use Furybee\MigrationRadar\Domain\Rule\Issue;
use Furybee\MigrationRadar\Domain\Rule\Rule;
use Furybee\MigrationRadar\Domain\Rule\Severity;

final readonly class RawSqlRule implements Rule
{
    private const EXCERPT_LENGTH = 80;

    public function id(): string
    {
        return 'raw-sql';
    }

    public function description(): string
    {
        return 'Raw SQL, which cannot be analysed';
    }

    public function check(Migration $migration, DatabaseContext $context): array
    {
        $issues = [];
        foreach ($migration->operations as $operation) {
            if (!$operation instanceof RawStatement) {
                continue;
            }

            $issues[] = new Issue(
                $this->id(),
                Severity::Info,
                'Raw SQL is not analysed: ' . $this->excerpt($operation->sql),
                'Check by hand which locks it takes and how long it runs on production data.',
                $migration->path,
                $operation->line,
            );
        }

        return $issues;
    }

    private function excerpt(?string $sql): string
    {
        if ($sql === null) {
            return '(dynamic query)';
        }

        $singleLine = trim((string) preg_replace('/\s+/', ' ', $sql));

        return mb_strlen($singleLine) > self::EXCERPT_LENGTH ? mb_substr($singleLine, 0, self::EXCERPT_LENGTH) . '...' : $singleLine;
    }
}
