<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Queue;

use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

abstract class QueueTestCase extends TestCase
{
    protected AdapterInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResourceConnection $resource */
        $resource = $this->objectManager->get(ResourceConnection::class);
        $this->connection = $resource->getConnection();
    }

    protected function clearQueueTable(): void
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
    }

    /**
     * Seeds a test job into the queue.
     *
     * The store_id column is always written explicitly (not only inside the payload JSON):
     * MAGE-1742 will make the column the source of truth for claiming, so the claim tests
     * must not depend on the payload fallback in Job::prepare().
     *
     * @param array $overrides column values on top of a valid buildIndexList job; pass
     *                         'store_id' => null for a legacy store-agnostic row
     */
    protected function seedJob(array $overrides = []): void
    {
        $storeId = array_key_exists('store_id', $overrides) ? $overrides['store_id'] : 1;

        $row = array_merge([
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => json_encode(['storeId' => (string) $storeId, 'entityIds' => ['9', '22']]),
            'max_retries' => 3,
            'retries' => 0,
            'error_log' => '',
            'data_size' => 2,
            'store_id' => $storeId,
            'is_full_reindex' => 0,
        ], $overrides);

        $this->connection->insert('algoliasearch_queue', $row);
    }

    /**
     * Raw queue rows as the DB stores them, ordered by insertion. Used to assert on the
     * claim side effects (pid and locked_at stamping) rather than on in-memory job objects,
     * which mirror the same values.
     *
     * @return array<int, array{job_id: string, store_id: ?string, pid: ?string, locked_at: ?string}>
     */
    protected function fetchQueueRows(): array
    {
        return $this->connection->query(
            'SELECT job_id, store_id, pid, locked_at FROM algoliasearch_queue ORDER BY job_id'
        )->fetchAll();
    }
}
