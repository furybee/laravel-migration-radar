<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

final readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isNullable = false,
        public bool $hasDefault = false,
        /** The default is an SQL expression (`DB::raw()`, `useCurrent()`), possibly volatile */
        public bool $hasExpressionDefault = false,
        /** Placed with `after()` or `first()` instead of being appended */
        public bool $isPositioned = false,
        /** Stored generated column (`storedAs()`), computed for every existing row */
        public bool $isStoredGenerated = false,
        public bool $isAutoIncrement = false,
        /** Virtual generated column (`virtualAs()`), computed on read */
        public bool $isVirtualGenerated = false,
    ) {
    }

    /**
     * Existing rows get a value without the application providing one.
     */
    public function isFilledAutomatically(): bool
    {
        return $this->isNullable || $this->hasDefault || $this->isAutoIncrement || $this->isStoredGenerated || $this->isVirtualGenerated;
    }
}
