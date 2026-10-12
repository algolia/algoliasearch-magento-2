<?php

namespace Algolia\AlgoliaSearch\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;

/**
 * Queue processing worker.
 *
 * When given store ID(s), the command drains only those stores' jobs under a per-store
 * lock and never spawns child processes. The parallel indexing orchestrator (MAGE-1731)
 * runs one worker per store and must preserve this invariant: workers are always given
 * a store ID and never spawn - this is the structural recursion guard.
 */
class ProcessQueueCommand extends AbstractStoreCommand
{
    protected const LOCK_NAME_PREFIX = 'algolia_queue_process_store_';

    public function __construct(
        protected State                     $state,
        protected StoreNameFetcher          $storeNameFetcher,
        protected ConfigHelper              $configHelper,
        protected Queue                     $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected LockManagerInterface      $lockManager,
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
        return 'Process the Algolia indexing queue for specified store(s) or all stores.';
    }

    protected function getStoreArgumentDescription(): string
    {
        return <<<'DESCRIPTION'
            ID(s) for store(s) to process the indexing queue for (optional),
            if not specified, the whole indexing queue is processed as a single sequential run.
            When store ID(s) are given, the command acts as a worker: it takes a per-store lock
            and skips any store whose queue is already being processed. Run one worker per store
            (e.g.: <info>bin/magento algolia:queue:process 2</info>)
            DESCRIPTION;
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setAliases(['algolia:reindex:process_queue']);
    }

    /**
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->setAreaCode();

        try {
            $storeIds = $this->getStoreIds($input);
            // Fails before any work when a store ID does not exist
            $storeNames = array_combine($storeIds, $this->storeNameFetcher->getStoreNames($storeIds));
        } catch (LocalizedException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        if ($storeIds === []) {
            return $this->processQueueForAllStores();
        }

        $hasFailure = false;
        foreach ($storeIds as $storeId) {
            if (!$this->processQueueForStore($storeId, $storeNames[$storeId])) {
                $hasFailure = true;
            }
        }

        return $hasFailure ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    /**
     * Sequential (no store ID) path - unchanged processing, but missing credentials
     * now report failure instead of the legacy success exit
     */
    protected function processQueueForAllStores(): int
    {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return Cli::RETURN_FAILURE;
        }

        try {
            $this->queue->runCron();
        } catch (Zend_Db_Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
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

        return true;
    }

    protected function getStoreLockName(int $storeId): string
    {
        return self::LOCK_NAME_PREFIX . $storeId;
    }
}
