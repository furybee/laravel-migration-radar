<?php

declare(strict_types=1);

namespace Furybee\MigrationRadar\Infrastructure\Git;

/**
 * Runs a command without a shell: arguments are never interpreted.
 */
final readonly class Process
{
    /**
     * @param list<string> $command
     * @return array{int, string, string} exit code, stdout, stderr
     */
    public function run(array $command, string $workingDirectory): array
    {
        // stderr goes to a file: reading two pipes one after the other deadlocks once the second one fills up
        $stderrFile = tmpfile();
        if ($stderrFile === false) {
            return [127, '', 'cannot create a temporary file'];
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $stderrFile], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            fclose($stderrFile);

            return [127, '', 'cannot start ' . $command[0]];
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        rewind($stderrFile);
        $stderr = (string) stream_get_contents($stderrFile);
        fclose($stderrFile);

        return [$exitCode, $stdout, $stderr];
    }
}
