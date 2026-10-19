<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Migration\Operation;

enum IndexType: string
{
    case Index = 'index';
    case Unique = 'unique';
    case Primary = 'primary';
    case FullText = 'fulltext';
    case Spatial = 'spatial';
}
