<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;

/**
 * Resolves the maximum number of queue worker processes to run in parallel.
 *
 * Priority order:
 *  1. An explicit CLI override (--max-processes)
 *  2. A statically configured value (max parallel processes > 0)
 *  3. Auto-detection: CPU core count minus one, capped by the configured auto cap
 *
 * An optional $pendingStores value further constrains the result, so workers are
 * never started for stores that have nothing to index.
 */
class MaxProcessesResolver
{
    /** Used when the auto cap config is unset or invalid. */
    public const int DEFAULT_AUTO_CAP = 4;
    /** Safe minimum when core detection fails or is unavailable. */
    public const int FALLBACK_CORE_COUNT = 1;

    protected const array CORE_DETECTION_COMMANDS = [
        'nproc',
        'sysctl -n hw.ncpu',
        'getconf _NPROCESSORS_ONLN',
    ];

    protected const int CORE_DETECTION_TIMEOUT_SECONDS = 1;

    public function __construct(
        protected QueueHelper $queueHelper,
    ) {
    }

    /**
     * @param int|null $cliOverride   Value passed via --max-processes; ignored unless >= 1
     * @param int|null $pendingStores If given, caps the result at this count (never below 0)
     */
    public function resolve(?int $cliOverride = null, ?int $pendingStores = null): int
    {
        if ($cliOverride !== null && $cliOverride >= 1) {
            $limit = $cliOverride;
        } else {
            $limit = $this->resolveFromConfig();
        }

        if ($pendingStores !== null) {
            $limit = min($limit, max($pendingStores, 0));
        }

        return $limit;
    }

    protected function resolveFromConfig(): int
    {
        $configured = $this->queueHelper->getMaxParallelProcesses();
        if ($configured > 0) {
            return $configured;
        }

        $cap = $this->queueHelper->getAutoParallelProcessesCap();
        if ($cap < 1) {
            $cap = self::DEFAULT_AUTO_CAP;
        }

        try {
            $cores = $this->detectCpuCores();
        } catch (\Throwable) {
            $cores = self::FALLBACK_CORE_COUNT;
        }

        return min(max(1, $cores - 1), $cap);
    }

    /**
     * Covers both a missing proc_open and one disabled via disable_functions
     * (PHP removes disabled functions from the function table).
     */
    public function isProcessSpawningSupported(): bool
    {
        return function_exists('proc_open') && '\\' !== \DIRECTORY_SEPARATOR;
    }

    protected function detectCpuCores(): int
    {
        if (!$this->isProcessSpawningSupported()) {
            return self::FALLBACK_CORE_COUNT;
        }

        foreach (self::CORE_DETECTION_COMMANDS as $command) {
            $cores = $this->readCoresFromCommand($command);
            if ($cores > 0) {
                return $cores;
            }
        }

        return self::FALLBACK_CORE_COUNT;
    }

    /**
     * Runs a core-count command and reads its stdout. Returns 0 on any failure,
     * so the caller can fall through to the next detection command.
     */
    protected function readCoresFromCommand(string $command): int
    {
        // Open the command as a child process. Descriptor spec 1 is stdout
        // (readable pipe) and 2 is stderr (readable pipe); $pipes receives
        // the file handles indexed the same way. "@" suppresses the warning
        // when the command does not exist, e.g. nproc on macOS.
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return 0;
        }

        [$stdout, $stderr] = $pipes;
        // Non-blocking streams so a hung command cannot stall stream_get_contents.
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $output = '';
        // Hard wall-clock budget; detection must never delay queue startup.
        $deadline = microtime(true) + self::CORE_DETECTION_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            // stream_select waits up to 50ms for stdout to become readable,
            // then $read is reduced to the streams that have data ready.
            $read = [$stdout];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 50000) > 0) {
                $chunk = stream_get_contents($stdout);
                if ($chunk !== false) {
                    $output .= $chunk;
                }
            }

            // Exit once the child has finished AND its output buffer is drained.
            if (!proc_get_status($process)['running'] && feof($stdout)) {
                break;
            }
        }

        // Timed out with the child still running: kill it and use what we have.
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }

        // Close pipes before proc_close, which waits for the child to exit.
        fclose($stdout);
        fclose($stderr);
        proc_close($process);

        return (int) trim($output);
    }
}
