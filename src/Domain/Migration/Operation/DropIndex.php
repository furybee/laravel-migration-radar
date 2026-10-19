<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

final readonly class DropIndex implements Operation
{
    public function __construct(
        public string $tableName,
        public int $line,
    ) {
    }

    public function table(): string
    {
        return $this->tableName;
    }

    public function line(): int
    {
        return $this->line;
    }
}
