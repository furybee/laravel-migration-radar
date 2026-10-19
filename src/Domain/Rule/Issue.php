<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule;

final readonly class Issue
{
    public function __construct(
        public string $ruleId,
        public Severity $severity,
        public string $message,
        public string $recommendation,
        public string $file,
        public int $line,
    ) {
    }
}
