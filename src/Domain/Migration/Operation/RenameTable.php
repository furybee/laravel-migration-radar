<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

final readonly class RenameTable implements Operation
{
    public function __construct(
        public string $from,
        public string $to,
        public int $line,
    ) {
    }

    public function table(): string
    {
        return $this->from;
    }

    public function line(): int
    {
        return $this->line;
    }
}
