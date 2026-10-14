<?php

namespace Algolia\AlgoliaSearch\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearQueueCommand extends AbstractStoreCommand
{
    public function __construct(
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
        protected JobResourceModel      $jobResourceModel,
        ?string                         $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'queue:';
    }

    protected function getCommandName(): string
    {
        return 'clear';
    }

    protected function getCommandDescription(): string
    {
        return 'Clear the indexing queue for specified store(s) or all stores. This will remove all pending indexing jobs from the queue.';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to clear indexing queue (optional), if not specified, indexing queue for all stores will be cleared. Pass multiple store IDs as a list of integers separated by spaces. e.g.: <info>bin/magento algolia:queue:clear 1 2 3</info>';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->setAreaCode();

        if (!$this->confirmClearOperation()) {
            return Cli::RETURN_SUCCESS;
        }

        $storeIds = $this->getStoreIds($input);

        $output->writeln($this->decorateOperationAnnouncementMessage('Clearing indexing queue for {{target}}', $storeIds));

        try {
            $this->clearQueue($storeIds);
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Indexing queue cleared successfully!</info>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Confirm the clear operation as it's destructive
     *
     */
    protected function confirmClearOperation(): bool
    {
        $this->output->writeln('<fg=red>WARNING:</fg=red> This will clear all pending indexing jobs from the queue for the specified store(s). This action cannot be undone!');

        return $this->confirmOperation(
            'Indexing queue clear operation confirmed',
            'Indexing queue clear operation cancelled',
            true
        );
    }

    /**
     * Clear indexing queue for specified stores or all stores
     *
     * @throws NoSuchEntityException|LocalizedException
     */
    protected function clearQueue(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->clearQueueForStore($storeId);
            }
        } else {
            $this->clearQueueForAllStores();
        }
    }

    /**
     * Clear indexing queue for a specific store
     *
     * @throws NoSuchEntityException
     */
    protected function clearQueueForStore(int $storeId): void
    {
        $storeName = $this->storeNameFetcher->getStoreName($storeId);
        $this->output->writeln('<info>Clearing indexing queue for ' . $storeName . '...</info>');

        try {
            $this->clearQueueTableForStore($storeId);

            $this->output->writeln('<info>✓ Indexing queue cleared for ' . $storeName . '</info>');
        } catch (\Exception $e) {
            $this->output->writeln('<error>✗ Failed to clear indexing queue for ' . $storeName . ': ' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Clear indexing queue for all stores
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function clearQueueForAllStores(): void
    {
        $connection = $this->jobResourceModel->getConnection();
        $connection->truncateTable($this->jobResourceModel->getMainTable());
    }

    /**
     * Clear the queue for a specific store
     * Deletes jobs scoped by the store_id column
     */
    protected function clearQueueTableForStore(int $storeId): void
    {
        $connection = $this->jobResourceModel->getConnection();
        $mainTable = $this->jobResourceModel->getMainTable();

        $deletedCount = $connection->delete(
            $mainTable,
            ['store_id = ?' => $storeId]
        );

        if ($deletedCount === 0) {
            $this->output->writeln('<comment>No jobs found for store ID ' . $storeId . '</comment>');

            return;
        }

        $this->output->writeln('<info>Deleted ' . $deletedCount . ' jobs for store ID ' . $storeId . '</info>');
    }
}
