<?php

namespace Algolia\AlgoliaSearch\Console\Command\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Service\Queue\QueueOrchestrator;
use Algolia\AlgoliaSearch\Service\Queue\QueueWorker;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporterFactory;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Queue processing command with two modes.
 *
 * WORKER MODE
 * With explicit store ID(s) the command runs in worker mode: it drains only those stores'
 * jobs under a per-store lock and never spawns child processes.
 * (Workers are always given a store ID and never spawn.)
 *
 * ORCHESTRATOR MODE
 * Without store IDs, the command runs in orchestrator mode when the effective maximum
 * parallel processes is greater than 1. It discovers the stores with pending jobs and
 * spawns one worker per store, up to the concurrency cap.
 */
class ProcessQueueCommand extends AbstractStoreCommand
{
    protected const string MAX_PROCESSES_OPTION = 'max-processes';
    protected const string SEQUENTIAL_OPTION = 'sequential';
    protected const string RUN_ID_OPTION = 'run-id';

    public function __construct(
        protected State                       $state,
        protected StoreNameFetcher            $storeNameFetcher,
        protected MaxProcessesResolver        $maxProcessesResolver,
        protected QueueOrchestrator           $queueOrchestrator,
        protected QueueWorker                 $queueWorker,
        protected ConsoleQueueReporterFactory $consoleQueueReporterFactory,
        ?string                               $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function createConsoleReporter(?OutputInterface $output = null): QueueReporterInterface
    {
        return $this->consoleQueueReporterFactory->create(['output' => $output ?? $this->output]);
    }

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'queue:';
    }

    protected function getCommandName(): string
    {
        return 'process';
    }

    protected function getCommandDescription(): string
    {
        return 'Process the Algolia indexing queue for specified store(s) or all stores. '
        . 'Operates in sequential, orchestrator or worker mode.';
    }

    protected function getStoreArgumentDescription(): string
    {
        return <<<'DESCRIPTION'
            ID(s) for store(s) to process the indexing queue for (optional),
            if not specified, the whole indexing queue is processed.
            When store ID(s) are given, the command acts as a worker: it takes a per-store lock
            and skips any store whose queue is already being processed. Run one worker per store
            (e.g.: <info>bin/magento algolia:queue:process 2</info>). If multiple store IDs are
            passed to a single worker, those stores will be processed sequentially in First In,
            First Out (FIFO) order.
            DESCRIPTION;
    }

    protected function getAdditionalDefinition(): array
    {
        return [
            new InputOption(
                self::MAX_PROCESSES_OPTION,
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum number of parallel worker processes the orchestrator may spawn. '
                . 'Takes precedence over the admin configuration.'
            ),
            new InputOption(
                self::SEQUENTIAL_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Safety override: Process the whole queue sequentially, bypassing parallel orchestration.'
            ),
            new InputOption(
                self::RUN_ID_OPTION,
                null,
                InputOption::VALUE_REQUIRED,
                'Internal: run correlation ID set by the orchestrator on its worker invocations. '
                . 'Recorded on the queue log rows this process writes.'
            ),
        ];
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setAliases(['algolia:reindex:process_queue']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->setAreaCode();

        try {
            $maxProcessesOverride = $this->getMaxProcessesOverride($input);
            $storeIds = $this->getStoreIds($input);
        } catch (LocalizedException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        // Explicit store IDs always mean worker mode: --sequential and --max-processes are ignored
        return $storeIds
            ? $this->runWorkerMode($storeIds, $this->getRunId($input))
            : $this->runBatchMode($input, $maxProcessesOverride);
    }

    protected function getRunId(InputInterface $input): ?string
    {
        $runId = $input->getOption(self::RUN_ID_OPTION);

        return $runId !== null ? (string) $runId : null;
    }

    protected function runWorkerMode(array $storeIds, ?string $runId = null): int
    {
        try {
            // Fails before any work when a store ID does not exist
            $this->storeNameFetcher->resolveStoreNames($storeIds, true);
        } catch (LocalizedException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        return $this->queueWorker->processStores($storeIds, $this->createConsoleReporter(), $runId);
    }

    protected function runBatchMode(InputInterface $input, ?int $maxProcessesOverride): int
    {
        if ($input->getOption(self::SEQUENTIAL_OPTION)) {
            return $this->runSequentialMode($maxProcessesOverride);
        }

        return $this->runOrchestratorMode($maxProcessesOverride);
    }

    protected function runSequentialMode(?int $maxProcessesOverride): int
    {
        if ($maxProcessesOverride !== null) {
            $this->output->writeln(
                '<comment>Option --' . self::SEQUENTIAL_OPTION . ' takes precedence over --'
                . self::MAX_PROCESSES_OPTION . ', processing the queue sequentially.</comment>'
            );
        }

        return $this->queueOrchestrator->runSequential($this->createConsoleReporter());
    }

    protected function runOrchestratorMode(?int $maxProcessesOverride): int
    {
        $maxProcesses = $this->maxProcessesResolver->resolve($maxProcessesOverride);
        $this->output->writeln('<info>Maximum parallel queue processes: ' . $maxProcesses . '</info>');

        if ($maxProcesses <= 1) {
            return $this->queueOrchestrator->runSequential($this->createConsoleReporter());
        }

        if (!$this->maxProcessesResolver->isProcessSpawningSupported()) {
            $this->output->writeln(
                '<error>PHP proc_open is disabled, falling back to sequential processing.</error>'
            );

            return $this->queueOrchestrator->runSequential($this->createConsoleReporter());
        }

        return $this->queueOrchestrator->orchestrate(
            $maxProcessesOverride,
            $this->createConsoleReporter()
        );
    }

    /**
     * @throws LocalizedException
     */
    protected function getMaxProcessesOverride(InputInterface $input): ?int
    {
        $value = $input->getOption(self::MAX_PROCESSES_OPTION);

        if ($value === null) {
            return null;
        }

        if (!ctype_digit((string) $value) || (int) $value < 1) {
            throw new LocalizedException(__(
                'Option --%1 must be an integer greater than or equal to 1 (e.g. --%1=4), "%2" given.',
                self::MAX_PROCESSES_OPTION,
                $value
            ));
        }

        if ($this->getStoreIds($input)) {
            throw new LocalizedException(__(
                'Option --%1 cannot be used in store worker mode.',
                self::MAX_PROCESSES_OPTION
            ));
        }

        return (int) $value;
    }
}
