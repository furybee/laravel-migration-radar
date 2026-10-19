<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Application\Port;

interface ChangedFiles
{
    /**
     * Files added or modified since $reference, committed or not.
     *
     * @return list<string>
     * @throws ChangedFilesUnavailable
     */
    public function since(string $reference): array;
}
