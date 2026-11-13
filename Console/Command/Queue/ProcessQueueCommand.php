<?php

namespace Algolia\AlgoliaSearch\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
use Algolia\AlgoliaSearch\Service\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;

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
    public const string STORE_LOCK_NAME_PREFIX = 'algolia_queue_process_store_';
    protected const string MAX_PROCESSES_OPTION = 'max-processes';
    protected const string SEQUENTIAL_OPTION = 'sequential';

    public function __construct(
        protected State                     $state,
        protected StoreNameFetcher          $storeNameFetcher,
        protected ConfigHelper              $configHelper,
        protected Queue                     $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected LockManagerInterface      $lockManager,
        protected MaxProcessesResolver      $maxProcessesResolver,
        protected QueueOrchestrator         $queueOrchestrator,
        ?string                             $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
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
            passed to a single worker, those stores will be processed sequentially FIFO.
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
        if ($storeIds) {
            try {
                // Fails before any work when a store ID does not exist
                $storeNames = $this->storeNameFetcher->resolveStoreNames($storeIds, true);
            } catch (LocalizedException $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');

                return Cli::RETURN_FAILURE;
            }

            $hasFailure = false;
            foreach ($storeIds as $storeId) {
                if (!$this->processQueueForStore($storeId, $storeNames[$storeId])) {
                    $hasFailure = true;
                }
            }

            return $hasFailure ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
        }

        $sequential = $input->getOption(self::SEQUENTIAL_OPTION);

        if ($sequential) {
            if ($maxProcessesOverride !== null) {
                $this->output->writeln(
                    '<comment>Option --' . self::SEQUENTIAL_OPTION . ' takes precedence over --'
                    . self::MAX_PROCESSES_OPTION . ', processing the queue sequentially.</comment>'
                );
            }

            return $this->queueOrchestrator->runSequential(new ConsoleQueueReporter($this->output));
        }

        $maxProcesses = $this->maxProcessesResolver->resolve($maxProcessesOverride);
        $this->output->writeln('<info>Maximum parallel queue processes: ' . $maxProcesses . '</info>');

        if ($maxProcesses <= 1) {
            return $this->queueOrchestrator->runSequential(new ConsoleQueueReporter($this->output));
        }

        if (!$this->maxProcessesResolver->isProcessSpawningSupported()) {
            $this->output->writeln(
                '<error>PHP proc_open is disabled, falling back to sequential processing.</error>'
            );

            return $this->queueOrchestrator->runSequential(new ConsoleQueueReporter($this->output));
        }

        return $this->queueOrchestrator->orchestrate(
            $maxProcessesOverride,
            new ConsoleQueueReporter($this->output),
            $this->output
        );
    }

    /**
     * Worker path for a single store. A held lock means another process (cron or worker)
     * is already draining that store: skip it with a warning and let the remaining stores run.
     * Missing credentials and database failures report the error and count as failures;
     * a store that fails processing does not abort the remaining stores.
     */
    protected function processQueueForStore(int $storeId, string $storeName): bool
    {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return false;
        }

        $lockName = $this->getStoreLockName($storeId);

        if (!$this->lockManager->lock($lockName, 0)) {
            $this->output->writeln(
                '<comment>Indexing queue is already being processed for ' . $storeName
                . ' (ID ' . $storeId . '), skipping.</comment>'
            );

            return true;
        }

        try {
            $this->queue->runCron(null, false, $storeId);
        } catch (Zend_Db_Exception $e) {
            $this->output->writeln(
                '<error>Failed to process indexing queue for ' . $storeName . ' (ID ' . $storeId . '): '
                . $e->getMessage() . '</error>'
            );

            return false;
        } finally {
            $this->lockManager->unlock($lockName);
        }

        // The orchestrator keys on this exit code to report the store as failed in its summary
        $failedJobs = $this->queue->getNoOfFailedJobs($storeId);
        if ($failedJobs > 0) {
            $this->output->writeln(
                '<error>' . $failedJobs . ' indexing job(s) failed for ' . $storeName
                . ' (ID ' . $storeId . ').</error>'
            );

            return false;
        }

        return true;
    }

    protected function getStoreLockName(int $storeId): string
    {
        return self::STORE_LOCK_NAME_PREFIX . $storeId;
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
