<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Tests\Support;

use Furybee\MigrationRadar\Domain\Migration\Migration;
use Furybee\MigrationRadar\Domain\Migration\Operation\Operation;
use Furybee\MigrationRadar\Domain\Migration\Suppression;

final class MigrationBuilder
{
    public const PATH = 'database/migrations/2026_01_01_000000_test.php';

    /** @var list<Operation> */
    private array $operations = [];

    /** @var list<Suppression> */
    private array $suppressions = [];

    public static function migration(): self
    {
        return new self();
    }

    public function with(Operation ...$operations): self
    {
        array_push($this->operations, ...array_values($operations));

        return $this;
    }

    public function suppressing(Suppression $suppression): self
    {
        $this->suppressions[] = $suppression;

        return $this;
    }

    public function build(): Migration
    {
        return new Migration(self::PATH, $this->operations, $this->suppressions);
    }
}
