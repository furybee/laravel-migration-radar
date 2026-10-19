<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Git;

use Furybee\MigrationRadar\Application\Port\ChangedFiles;
use Furybee\MigrationRadar\Application\Port\ChangedFilesUnavailable;

/**
 * Paths are relative to the working directory, like the paths given to the analysis.
 */
final readonly class GitChangedFiles implements ChangedFiles
{
    private const COMMAND_NOT_FOUND = 127;

    public function __construct(
        private string $workingDirectory,
        private Process $process = new Process(),
    ) {
    }

    public function since(string $reference): array
    {
        if ($reference === '' || str_starts_with($reference, '-')) {
            throw new ChangedFilesUnavailable("Invalid git reference \"{$reference}\"");
        }

        // Compares the working tree with the merge base: committed and uncommitted changes of the branch
        $tracked = $this->git(['diff', '--name-only', '--diff-filter=AMR', '--relative', '--merge-base', $reference]);
        $untracked = $this->git(['ls-files', '--others', '--exclude-standard']);

        return array_values(array_unique([...$tracked, ...$untracked]));
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function git(array $arguments): array
    {
        [$exitCode, $stdout, $stderr] = $this->process->run(['git', ...$arguments], $this->workingDirectory);
        if ($exitCode === self::COMMAND_NOT_FOUND) {
            throw new ChangedFilesUnavailable('git is not installed, it is required by --since');
        }
        if ($exitCode !== 0) {
            throw new ChangedFilesUnavailable('git ' . implode(' ', $arguments) . ' failed: ' . trim($stderr));
        }

        return array_values(array_filter(array_map('trim', explode("\n", $stdout)), static fn(string $line) => $line !== ''));
    }
}
