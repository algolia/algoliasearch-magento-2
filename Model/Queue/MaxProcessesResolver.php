<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;

class MaxProcessesResolver
{
    public const int DEFAULT_AUTO_CAP = 4;
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

    protected function detectCpuCores(): int
    {
        if (!function_exists('proc_open')) {
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

    protected function readCoresFromCommand(string $command): int
    {
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return 0;
        }

        [$stdout, $stderr] = $pipes;
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $output = '';
        $deadline = microtime(true) + self::CORE_DETECTION_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $read = [$stdout];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 50000) > 0) {
                $chunk = stream_get_contents($stdout);
                if ($chunk !== false) {
                    $output .= $chunk;
                }
            }

            if (!proc_get_status($process)['running'] && feof($stdout)) {
                break;
            }
        }

        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }

        fclose($stdout);
        fclose($stderr);
        proc_close($process);

        return (int) trim($output);
    }
}
