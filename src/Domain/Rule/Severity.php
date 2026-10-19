<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Rule;

enum Severity: string
{
    /** Locks or breaks production */
    case Error = 'error';
    /** Risky, needs a deliberate decision */
    case Warning = 'warning';
    /** Cannot be analysed, review it by hand */
    case Info = 'info';

    public function isAtLeast(self $threshold): bool
    {
        return $this->weight() >= $threshold->weight();
    }

    /**
     * On an unknown platform the risk is only possible: errors become warnings.
     */
    public function onPlatformKnown(bool $isPlatformKnown): self
    {
        return $this === self::Error && !$isPlatformKnown ? self::Warning : $this;
    }

    private function weight(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Error => 2,
        };
    }
}
