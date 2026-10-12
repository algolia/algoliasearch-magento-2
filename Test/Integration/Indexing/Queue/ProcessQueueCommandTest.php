<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Framework\Console\Cli;
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
class ProcessQueueCommandTest extends QueueTestCase
{
    /** @var StoreRepositoryInterface */
    private $storeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig(QueueHelper::IS_ACTIVE, '1');

        $this->storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);

        $this->clearQueueTable();
    }

    protected function tearDown(): void
    {
        $this->clearQueueTable();

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

        $defaultStoreId = (int) $defaultStore->getId();
        $secondStoreId = (int) $secondStore->getId();

        $this->seedJob([
            'store_id' => $defaultStoreId,
            'class' => IndicesConfigurator::class,
            'method' => 'saveConfigurationToAlgolia',
            'data' => json_encode([$defaultStoreId]),
            'data_size' => 1,
        ]);
        $this->seedJob([
            'store_id' => $secondStoreId,
            'class' => IndicesConfigurator::class,
            'method' => 'saveConfigurationToAlgolia',
            'data' => json_encode([$secondStoreId]),
            'data_size' => 1,
        ]);

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
}
