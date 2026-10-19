<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

final readonly class AddForeignKey implements Operation
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public string $tableName,
        public array $columns,
        public ?string $referencedTable,
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
