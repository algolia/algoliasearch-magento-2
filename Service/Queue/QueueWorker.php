<?php

namespace Algolia\AlgoliaSearch\Service\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Lock\LockManagerInterface;
use Zend_Db_Exception;

/**
 * Worker-side queue processing for explicit store IDs, used by the worker mode of the
 * algolia:queue:process command.
 *
 * Each store is drained under its own non-blocking lock, so the cron job and any number of
 * workers can run concurrently without double-processing. Workers never spawn processes and
 * never take a global credentials check: the gate is per store, because stores can be
 * configured individually.
 */
class QueueWorker
{
    public const string STORE_LOCK_NAME_PREFIX = 'algolia_queue_process_store_';

    public function __construct(
        protected Queue                     $queue,
        protected LockManagerInterface      $lockManager,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected StoreNameFetcher          $storeNameFetcher,
    ) {}

    /**
     * Drains the given stores sequentially, in the order given.
     *
     * @param int[] $storeIds
     *
     * @return bool Reports success of operation
     *              False when any store reported a failure. A store skipped because its lock was
     *              already held is a success: another process is draining it.
     */
    public function processStores(
        array $storeIds,
        QueueReporterInterface $reporter,
        ?string $runId = null
    ): bool {
        $hasFailure = false;
        foreach ($storeIds as $storeId) {
            if (!$this->processStore($storeId, $reporter, $runId)) {
                $hasFailure = true;
            }
        }

        return !$hasFailure;
    }

    /**
     * Worker path for a single store. A held lock means another process (cron or worker)
     * is already draining that store: skip it with a warning and let the remaining stores run.
     * Missing credentials and database failures report the error and count as failures;
     * a store that fails processing does not abort the remaining stores.
     */
    protected function processStore(
        int $storeId,
        QueueReporterInterface $reporter,
        ?string $runId = null
    ): bool {
        // If a store cannot be resolved, the label degrades to a plain ID label rather than throwing an exception
        // (e.g. it was deleted while its jobs were still pending)
        $label = $this->storeNameFetcher->getStoreLabel($storeId);

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            // Not AlgoliaCredentialsManager::displayErrorMessage(): it writes to its own console
            // output, which would bypass the reporter funnel.
            $reporter->error($this->algoliaCredentialsManager->getErrorMessage(self::class, $storeId));

            return false;
        }

        $lockName = $this->getStoreLockName($storeId);

        if (!$this->lockManager->lock($lockName, 0)) {
            $reporter->warning(
                'Indexing queue is already being processed for ' . $label . ', skipping.'
            );

            return true;
        }

        try {
            $this->queue->runCron(nbJobs: null, force: false, storeId: $storeId, runId: $runId);
        } catch (Zend_Db_Exception $e) {
            $reporter->error(
                'Failed to process indexing queue for ' . $label . ': ' . $e->getMessage()
            );

            return false;
        } finally {
            $this->lockManager->unlock($lockName);
        }

        $failedJobs = $this->queue->getNoOfFailedJobs($storeId);
        if ($failedJobs > 0) {
            $reporter->error($failedJobs . ' indexing job(s) failed for ' . $label . '.');

            return false;
        }

        return true;
    }

    protected function getStoreLockName(int $storeId): string
    {
        return self::STORE_LOCK_NAME_PREFIX . $storeId;
    }
}
