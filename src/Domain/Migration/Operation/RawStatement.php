<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

final readonly class RawStatement implements Operation
{
    public function __construct(
        public ?string $sql,
        public int $line,
    ) {
    }

    public function table(): ?string
    {
        return null;
    }

    public function line(): int
    {
        return $this->line;
    }
}
