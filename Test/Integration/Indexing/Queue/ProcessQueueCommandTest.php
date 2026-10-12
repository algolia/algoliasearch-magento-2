<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @magentoDataFixture Magento/Store/_files/second_store.php
 *
 * @magentoDbIsolation disabled
 *
 * @magentoAppIsolation enabled
 */
class ProcessQueueCommandTest extends TestCase
{
    /** @var AdapterInterface */
    private $connection;

    /** @var StoreRepositoryInterface */
    private $storeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig(QueueHelper::IS_ACTIVE, '1');

        /** @var ResourceConnection $resource */
        $resource = $this->objectManager->get(ResourceConnection::class);
        $this->connection = $resource->getConnection();

        $this->storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
    }

    protected function tearDown(): void
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        parent::tearDown();
    }

    /**
     * Worker mode must scope the whole drain to the requested store: its jobs are claimed
     * and executed, while every other store's rows stay unclaimed (pid IS NULL) and
     * available for their own workers.
     */
    public function testWorkerModeDrainsOnlyRequestedStore()
    {
        $defaultStore = $this->storeRepository->get('default');
        $secondStore = $this->storeRepository->get('fixture_second_store');

        $this->seedJob((int) $defaultStore->getId());
        $this->seedJob((int) $secondStore->getId());

        [$exitCode] = $this->runCommand([(int) $defaultStore->getId()]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);

        $rows = $this->fetchQueueRows();

        $defaultStoreRows = array_values(array_filter(
            $rows,
            fn (array $row) => (int) $row['store_id'] === (int) $defaultStore->getId()
        ));
        $this->assertCount(0, $defaultStoreRows, 'Requested store jobs must be drained');

        $secondStoreRows = array_values(array_filter(
            $rows,
            fn (array $row) => (int) $row['store_id'] === (int) $secondStore->getId()
        ));
        $this->assertCount(1, $secondStoreRows, 'Other store job must remain in the queue');
        $this->assertNull($secondStoreRows[0]['pid'], 'Other store job must stay unclaimed');
        $this->assertNull($secondStoreRows[0]['locked_at'], 'Other store job must stay unlocked');
    }

    /**
     * @return array{0: int, 1: BufferedOutput}
     */
    private function runCommand(array $storeIds): array
    {
        /** @var ProcessQueueCommand $command */
        $command = $this->objectManager->get(ProcessQueueCommand::class);
        $this->invokeMethod($command, 'configure');

        $input = new ArrayInput(['store_id' => $storeIds]);
        $input->bind($command->getDefinition());

        $output = new BufferedOutput();
        $exitCode = $this->invokeMethod($command, 'execute', [$input, $output]);

        return [$exitCode, $output];
    }

    /**
     * Seeds one runnable job (saveConfigurationToAlgolia is a valid handler that succeeds
     * against the real Algolia application) so a drain assertion cannot be satisfied by
     * claiming alone - the job must actually execute to leave the queue.
     */
    private function seedJob(int $storeId): void
    {
        $this->connection->insert('algoliasearch_queue', [
            'created' => date('Y-m-d H:i:s'),
            'pid' => null,
            'class' => IndicesConfigurator::class,
            'method' => 'saveConfigurationToAlgolia',
            'data' => json_encode([$storeId]),
            'max_retries' => 3,
            'retries' => 0,
            'error_log' => '',
            'data_size' => 1,
            'store_id' => $storeId,
            'is_full_reindex' => 0,
        ]);
    }

    /**
     * @return array<int, array{job_id: string, store_id: string, pid: ?string, locked_at: ?string}>
     */
    private function fetchQueueRows(): array
    {
        return $this->connection->query(
            'SELECT job_id, store_id, pid, locked_at FROM algoliasearch_queue ORDER BY job_id'
        )->fetchAll();
    }
}
