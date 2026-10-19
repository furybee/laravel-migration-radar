<div align="center">

# Laravel Migration Radar

**Catch dangerous database migrations before they reach production — aware of your table sizes and database version.**

[![CI](https://github.com/furybee/laravel-migration-radar/actions/workflows/ci.yml/badge.svg)](https://github.com/furybee/laravel-migration-radar/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-8892BF.svg)](https://www.php.net)

</div>

---

A migration that runs in a second on your laptop can lock a 50-million-row table for twenty minutes in production,
or break the code still running while the release rolls out. Migration Radar reads your Laravel migrations
without running them and reports those operations, with the safe way to do the same thing.

What makes it different from a list of "never do X" rules:

- **It knows your database.** Adding a column is instant on MySQL 8.0.29 but rebuilds the table on 5.7; an index blocks
  writes on PostgreSQL but not on InnoDB. Rules follow the engine and version you target.
- **It knows your table sizes.** Lock and rebuild rules only fire on tables large enough to matter, read from the
  database statistics or from your configuration. No noise on a 200-row settings table.
- **It fits in CI.** Analyse only the migrations of the branch (`--since=origin/main`), get GitHub annotations or a
  GitLab Code Quality report, fail on errors only or on warnings too.

```
database/migrations/2026_01_01_000000_add_foreign_key_to_users.php
    11  ERROR   add-foreign-key-locks-table
        Adding foreign key on (team_id) to "users" (~5,000,000 rows) copies the whole table while blocking writes (foreign_key_checks forces ALGORITHM=COPY).
        → Run it with an online schema change tool (gh-ost, pt-online-schema-change), or declare the foreign key when creating the table.

database/migrations/2026_01_02_000000_drop_users_legacy_column.php
    11  WARNING drop-column
        Dropping column "users.legacy": the code currently deployed fails if it still reads or writes it.
        → Remove every use of the column first (queries, model attributes, casts, fillable) and deploy it, then drop the column in a later release.

3 migration(s) analysed on MySQL 8.0.35: 1 error(s), 1 warning(s), 0 info, 1 ignored
```

## Installation

```bash
composer require --dev furybee/laravel-migration-radar
```

Requires PHP 8.2+. Supports MySQL, MariaDB and PostgreSQL migrations written with Laravel's schema builder.

## Usage

```bash
# Every migration of database/migrations
vendor/bin/migration-radar

# Only the migrations added or changed on the current branch: the ones that have not run in production yet
vendor/bin/migration-radar --since=origin/main

# Tell it the target database...
vendor/bin/migration-radar --platform=mysql:8.0.35

# ...or let it read the version and the table sizes (no table is scanned, only engine statistics)
MIGRATION_RADAR_DSN=mysql://readonly:secret@replica:3306/app vendor/bin/migration-radar

# List the rules
vendor/bin/migration-radar rules
```

| Option | Default | |
|--------|---------|---|
| `paths` (arguments) | `database/migrations` | Migration files or directories |
| `--since=REF` | | Only migrations added or modified since this git reference, committed or not |
| `--platform=P` | | `mysql:8.0.35`, `mariadb:10.11`, `pgsql:16`... wins over what the database reports |
| `--dsn=URL` | `MIGRATION_RADAR_DSN` | `mysql://`, `pgsql://` or `sqlite://` URL to read the version and table sizes |
| `--format=F` | `text` | `text`, `json`, `github` (annotations), `gitlab` (Code Quality report) |
| `--fail-on=S` | `error` | Lowest severity failing the command: `error`, `warning`, `info`, `never` |
| `--config=FILE` | `migration-radar.php` | Configuration file |

Exit codes: `0` no issue at or above `--fail-on`, `1` issues found, `2` invalid input (configuration, platform,
unreachable database, unknown git reference).

### Without database access

Give the platform with its full version and, optionally, the size of your big tables. Without any of this,
Migration Radar assumes an old version and large tables: it reports more than needed rather than missing an outage,
and tells you so in a hint.

```php
// migration-radar.php
return [
    'platform' => 'mysql:8.0.35',
    'table_rows' => ['users' => 4_000_000, 'audit_logs' => 120_000_000],
];
```

`mysql:8.0` without the patch number is read as 8.0.0: write the full version to get instant `ADD COLUMN` recognised.

## Rules

| Rule | Severity | Reports |
|------|----------|---------|
| `not-null-column-without-default` | error | A NOT NULL column without default added to an existing table: inserts from the code still deployed fail, PostgreSQL rejects it on a non-empty table |
| `add-column-rewrites-table` | error / warning | A column whose addition rebuilds a large table: MySQL < 8.0.12 (8.0.29 with `after()`), MariaDB < 10.3.2 (10.4 with `after()`), PostgreSQL < 11 with a default, volatile expression defaults, stored generated columns |
| `change-column` | error | `->change()` on a large table: copies (MySQL) or rewrites (PostgreSQL) it for most changes |
| `rename-column` | error | Renaming a column the deployed code still uses |
| `rename-table` | error | Renaming a table the deployed code still uses |
| `drop-column` | warning | Dropping a column the deployed code may still use |
| `drop-table` | warning | Dropping a table, which cannot be undone |
| `add-index-locks-table` | error / warning | An index blocking writes on a large table: any index on PostgreSQL (no `CONCURRENTLY`), full text and spatial indexes on InnoDB; a primary key rebuilds InnoDB tables |
| `add-foreign-key-locks-table` | error | A foreign key on a large table: InnoDB copies the table, PostgreSQL locks both tables while validating |
| `mixed-schema-and-data` | warning | Schema and data changed in the same migration: not transactional on MySQL, and backfills hold the locks |
| `raw-sql` | info | `DB::statement()` / `DB::unprepared()`, which cannot be analysed |

On an unknown platform, errors that depend on the engine are reported as warnings. Operations on a table created by
the same migration are never reported: it is empty.

Every issue comes with a recommendation: expand/contract steps, `CREATE INDEX CONCURRENTLY`, `NOT VALID` constraints,
online schema change tools (gh-ost, pt-online-schema-change).

### Ignoring an issue

Above the statement, with the rules and a reason:

```php
// migration-radar-ignore drop-column -- unused since the v2 API, removed in #1234
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('legacy_token');
});
```

Without rule ids the comment ignores every rule for that statement. In the configuration, `ignore` maps path globs to
rule ids (`*` for all), and `disabled_rules` turns rules off.

## Configuration

`migration-radar.php` at the root of the project, every key optional. See [`examples/migration-radar.php`](examples/migration-radar.php).

| Key | Default | |
|-----|---------|---|
| `paths` | `['database/migrations']` | Where migrations live |
| `platform` | | Target database, e.g. `mysql:8.0.35` |
| `dsn` | | Database URL, better passed as `MIGRATION_RADAR_DSN` |
| `large_table_rows` | `1_000_000` | Rows from which a table is large |
| `assume_large_tables` | `true` | Whether a table without statistics is presumed large |
| `table_rows` | `[]` | Row estimates per table, wins over the database statistics |
| `disabled_rules` | `[]` | Rule ids turned off |
| `ignore` | `[]` | Path glob => rule ids |

Unknown keys and invalid values are rejected with a clear message: a typo never silently changes the analysis.

## In CI

GitHub Actions, with annotations on the pull request:

```yaml
- run: vendor/bin/migration-radar --since=origin/${{ github.base_ref }} --format=github
  env:
    MIGRATION_RADAR_DSN: ${{ secrets.MIGRATION_RADAR_DSN }} # optional, a read-only replica
```

Fetch the base branch first (`actions/checkout` with `fetch-depth: 0`).

GitLab CI, with the Code Quality widget on the merge request:

```yaml
migration-radar:
  script:
    - git fetch origin $CI_MERGE_REQUEST_TARGET_BRANCH_NAME
    - vendor/bin/migration-radar --since=origin/$CI_MERGE_REQUEST_TARGET_BRANCH_NAME --format=gitlab > gl-code-quality-report.json
  artifacts:
    when: always
    reports:
      codequality: gl-code-quality-report.json
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
```

## How it works

Migrations are parsed, never executed. The `up()` method is read with [nikic/php-parser](https://github.com/nikic/PHP-Parser):
`Schema::create/table/drop/rename` calls, the `$table->...` calls of their closures (columns and modifiers, indexes,
foreign keys, drops, renames), `DB::statement()` and data writes (`DB::table()->update()`, Eloquent `update()`,
`chunkById()`...). Table names given as literals, class constants or concatenations are resolved.

Limits: a table name only known at runtime shows as `(dynamic)` and is presumed large (unless `assume_large_tables` is off); operations hidden in helper
methods or traits are not followed; raw SQL is reported, not analysed.

## Development

```bash
composer install
composer test        # unit and integration tests
composer analyse     # PHPStan, level max
```

The database integration tests run against real servers when these variables are set, and are skipped otherwise:

```bash
MIGRATION_RADAR_TEST_MYSQL_URL=mysql://root:radar@127.0.0.1/radar \
MIGRATION_RADAR_TEST_PGSQL_URL=pgsql://postgres:radar@127.0.0.1/radar \
vendor/bin/phpunit --testsuite integration
```

### Architecture

```
src/
├── Domain/          # Migration model, database context, rules. Pure PHP, no dependency.
│   ├── Migration/   #   What a migration does: operations, columns, ignore directives
│   ├── Database/    #   Platform (engine + version), table sizes
│   └── Rule/        #   Rule contract, issues, severities, one class per rule
├── Application/     # Use cases and the ports they need
│   ├── Analysis/    #   AnalyseMigrations, SelectMigrations, BuildDatabaseContext, RuleSet, ReportRenderer
│   ├── Configuration/
│   └── Port/        #   MigrationParser, MigrationFinder, ChangedFiles, DatabaseInspector
├── Infrastructure/  # Adapters: Laravel parser, PDO inspector, git, report formats, configuration file
└── Console/         # Symfony Console commands and the composition root (ServiceFactory)
```

Dependencies point inwards only: Domain depends on nothing, Application on Domain, Infrastructure on both.
CI enforces it with [furybee/php-deps](https://github.com/furybee/php-deps) (`php-deps.php`).

Adding a rule: a class implementing `Domain\Rule\Rule`, registered in `RuleSet::default()`, with its unit test built on
`Tests\Support\MigrationBuilder`. Supporting another framework: an implementation of `Application\Port\MigrationParser`.

## License

[MIT](LICENSE)
