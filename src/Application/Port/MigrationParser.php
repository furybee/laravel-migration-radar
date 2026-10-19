<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Port;

use Furybee\MigrationRadar\Domain\Migration\Migration;

interface MigrationParser
{
    /**
     * @throws MigrationNotParsable
     */
    public function parse(string $path): Migration;
}
