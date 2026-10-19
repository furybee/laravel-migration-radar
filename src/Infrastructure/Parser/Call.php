<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Parser;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;

final readonly class Call
{
    /**
     * @param list<Arg> $args
     */
    public function __construct(
        public string $name,
        public array $args,
        public int $line,
    ) {
    }

    public function argument(int $position): ?Expr
    {
        return ($this->args[$position] ?? null)?->value;
    }
}
