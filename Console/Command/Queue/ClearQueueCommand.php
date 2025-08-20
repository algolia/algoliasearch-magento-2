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
        return "Clear the indexing queue for specified store(s) or all stores. This will remove all pending indexing jobs from the queue.";
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
     * @return bool
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
     * @param array $storeIds
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
     * @param int $storeId
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
     * Clear the queue for a specific store (2 different approaches)
     * Filters jobs by store_id in the JSON data field and deletes them
     *
     * @param int $storeId
     * @throws \Exception
     */
    protected function clearQueueTableForStore(int $storeId): void
    {
        try {
            // Use JSON_EXTRACT to filter by store_id in the data field
            // This assumes MySQL 5.7+ or MariaDB 10.2+ for JSON support
            $connection = $this->jobResourceModel->getConnection();
            $mainTable = $this->jobResourceModel->getMainTable();

            $select = $connection->select()
                ->from($mainTable, ['job_id'])
                ->where('JSON_EXTRACT(data, "$.storeId") = ?', $storeId);

            $jobIds = $connection->fetchCol($select);

            if (empty($jobIds)) {
                $this->output->writeln('<comment>No jobs found for store ID ' . $storeId . '</comment>');
                return;
            }

            // Delete the filtered jobs
            $deletedCount = $connection->delete(
                $mainTable,
                ['job_id IN (?)' => $jobIds]
            );

            $this->output->writeln('<info>Deleted ' . $deletedCount . ' jobs for store ID ' . $storeId . '</info>');

        } catch (\Exception $e) {
            // Fallback method if JSON_EXTRACT is not supported
            $this->output->writeln('<comment>JSON filtering not supported by database, using fallback method...</comment>');
            $this->clearQueueTableForStoreFallback($storeId);
        }
    }

    /**
     * Fallback method for clearing queue by store when JSON filtering is not supported
     * Loads all jobs and filters them in PHP
     *
     * @param int $storeId
     * @throws \Exception
     */
    protected function clearQueueTableForStoreFallback(int $storeId): void
    {
        try {
            $connection = $this->jobResourceModel->getConnection();
            $mainTable = $this->jobResourceModel->getMainTable();

            // Get all jobs and filter by store_id in PHP
            $select = $connection->select()
                ->from($mainTable, ['job_id', 'data'])
                ->where('data IS NOT NULL');

            $jobs = $connection->fetchAll($select);
            $jobsToDelete = [];

            foreach ($jobs as $job) {
                $data = json_decode($job['data'], true);
                if (isset($data['storeId']) && $data['storeId'] == $storeId) {
                    $jobsToDelete[] = $job['job_id'];
                }
            }

            if (empty($jobsToDelete)) {
                $this->output->writeln('<comment>No jobs found for store ID ' . $storeId . ' (fallback method)</comment>');
                return;
            }

            // Delete the filtered jobs
            $deletedCount = $connection->delete(
                $mainTable,
                ['job_id IN (?)' => $jobsToDelete]
            );

            $this->output->writeln('<info>Deleted ' . $deletedCount . ' jobs for store ID ' . $storeId . ' (fallback method)</info>');

        } catch (\Exception $e) {
            /**
             * Escaping complaints represent a false positive as sprintf is used to convert the $storeId to an int and the error message is generated internally
             * phpcs:ignore 
             */
            throw new \Exception(sprintf('Failed to clear queue for store %d: %s', $storeId, $e->getMessage()));
        }
    }
}
