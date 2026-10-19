<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Domain\Database;

enum Driver: string
{
    case MySql = 'mysql';
    case MariaDb = 'mariadb';
    case PostgreSql = 'pgsql';
    case Sqlite = 'sqlite';
    case Unknown = 'unknown';
}
