<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ClearQueueCommand;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ClearQueueCommandTest extends TestCase
{
    protected function createObjectToTest(
        ?State $state = null,
        ?StoreNameFetcher $storeNameFetcher = null,
        ?StoreManagerInterface $storeManager = null,
        ?JobResourceModel $jobResourceModel = null,
    ): ClearQueueCommand {
        return new ClearQueueCommand(
            $state ?? $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $jobResourceModel ?? $this->createStub(JobResourceModel::class),
        );
    }

    /**
     * A partial mock is needed to isolate the method under test from the sibling methods it
     * calls on the same class (e.g. execute() calling clearQueue()).
     */
    protected function createPartialObjectToTest(
        array $methodsToMock,
        ?State $state = null,
        ?StoreNameFetcher $storeNameFetcher = null,
        ?StoreManagerInterface $storeManager = null,
        ?JobResourceModel $jobResourceModel = null,
    ): ClearQueueCommand&MockObject {
        return $this->getMockBuilder(ClearQueueCommand::class)
            ->setConstructorArgs([
                $state ?? $this->createStub(State::class),
                $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
                $storeManager ?? $this->createStub(StoreManagerInterface::class),
                $jobResourceModel ?? $this->createStub(JobResourceModel::class),
                null,
            ])
            ->onlyMethods($methodsToMock)
            ->getMock();
    }

    public function testExecuteReturnsSuccessWhenUserCancels(): void
    {
        $cmd = $this->createPartialObjectToTest(
            ['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']
        );

        $cmd->expects($this->once())->method('setAreaCode');
        $cmd->expects($this->once())->method('confirmOperation')->willReturn(false);
        $cmd->expects($this->never())->method('getStoreIds');
        $cmd->expects($this->never())->method('clearQueue');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $code = $this->invokeMethod($cmd, 'execute', [$input, $output]);
        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteClearsProvidedStoreIds(): void
    {
        $cmd = $this->createPartialObjectToTest(
            ['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']
        );

        $cmd->method('setAreaCode');
        $cmd->method('confirmOperation')->willReturn(true);
        $cmd->method('getStoreIds')->willReturn([1, 2]);

        $msg = 'Clearing indexing queue for stores 1, 2';
        $cmd->method('decorateOperationAnnouncementMessage')->willReturn($msg);

        $cmd->expects($this->once())->method('clearQueue')->with([1, 2]);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $code = $this->invokeMethod($cmd, 'execute', [$input, $output]);
        $this->assertSame(Cli::RETURN_SUCCESS, $code);

        $this->assertStringContainsString($msg, $output->fetch());
    }

    public function testExecuteReturnsFailureOnClearException(): void
    {
        $cmd = $this->createPartialObjectToTest(
            ['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']
        );

        $cmd->method('setAreaCode');
        $cmd->method('confirmOperation')->willReturn(true);
        $cmd->method('getStoreIds')->willReturn([]);
        $cmd->method('decorateOperationAnnouncementMessage')->willReturn('Clearing indexing queue for all stores');

        $errMsg = 'Error encountered while attempting to clear queue.';
        $cmd->expects($this->once())->method('clearQueue')->willThrowException(new \Exception($errMsg));

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $code = $this->invokeMethod($cmd, 'execute', [$input, $output]);
        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString($errMsg, $output->fetch());
    }

    public function testClearQueueCallsPerStore(): void
    {
        $cmd = $this->createPartialObjectToTest(['clearQueueForStore', 'clearQueueForAllStores']);

        $expectedStoreIds = [1, 2];
        $callIndex = 0;
        $cmd->expects($this->exactly(2))
            ->method('clearQueueForStore')
            ->willReturnCallback(function ($storeId) use (&$callIndex, $expectedStoreIds) {
                $this->assertSame(
                    $expectedStoreIds[$callIndex],
                    $storeId,
                    "clearQueueForStore called with unexpected storeId at call $callIndex"
                );
                $callIndex++;
            });

        $cmd->expects($this->never())->method('clearQueueForAllStores');

        $this->invokeMethod($cmd, 'clearQueue', [[1, 2]]);
    }

    public function testClearQueueEmptyCallsAllStores(): void
    {
        $cmd = $this->createPartialObjectToTest(['clearQueueForStore', 'clearQueueForAllStores']);

        $cmd->expects($this->never())->method('clearQueueForStore');
        $cmd->expects($this->once())->method('clearQueueForAllStores');

        $this->invokeMethod($cmd, 'clearQueue');
    }

    public function testClearQueueForAllStoresTruncatesMainTable(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('truncateTable')->with('algoliasearch_queue');

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->invokeMethod($cmd, 'clearQueue');
    }

    public function testClearQueueForStoreSuccess(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreName')->willReturn('Default Store');

        $cmd = $this->createPartialObjectToTest(['clearQueueTableForStore'], storeNameFetcher: $storeNameFetcher);
        $output = new BufferedOutput();
        $this->setPrivateProperty($cmd, 'output', $output);

        $cmd->expects($this->once())->method('clearQueueTableForStore')->with(1);

        $this->invokeMethod($cmd, 'clearQueueForStore', [1]);

        $text = $output->fetch();
        $this->assertStringContainsString('Clearing indexing queue for Default Store', $text);
        $this->assertStringContainsString('Indexing queue cleared for Default Store', $text);
    }

    public function testClearQueueForStoreErrorPrinted(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreName')->willReturn('Default Store');

        $cmd = $this->createPartialObjectToTest(['clearQueueTableForStore'], storeNameFetcher: $storeNameFetcher);
        $output = new BufferedOutput();
        $this->setPrivateProperty($cmd, 'output', $output);

        $errorMsg = 'DB operation failed';
        $cmd->expects($this->once())->method('clearQueueTableForStore')->willThrowException(new \Exception($errorMsg));

        $this->invokeMethod($cmd, 'clearQueueForStore', [1]);

        $this->assertStringContainsString("Failed to clear indexing queue for Default Store: $errorMsg", $output->fetch());
    }

    public function testClearQueueForStoreJsonPathDeletesJobs(): void
    {
        $adapter = $this->createMockSearchAdapter();
        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter->expects($this->once())->method('fetchCol')->willReturn([10, 11]);
        $adapter->expects($this->once())
            ->method('delete')
            ->with('algoliasearch_queue', ['job_id IN (?)' => [10, 11]])
            ->willReturn(2);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('Deleted 2 jobs for store ID 5', $output->fetch());
    }

    public function testClearQueueForStoreJsonPathNoJobs(): void
    {
        $adapter = $this->createStubSearchAdapter();
        $adapter->method('fetchCol')->willReturn([]);

        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('No jobs found for store ID 5', $output->fetch());
    }

    public function testClearQueueForStoreFallsBackWhenJsonThrows(): void
    {
        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('select')->willThrowException(new \Exception('No JSON support'));

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);

        $cmd = $this->createPartialObjectToTest(['clearQueueTableForStoreFallback'], jobResourceModel: $jobResourceModel);
        $output = new BufferedOutput();
        $this->setPrivateProperty($cmd, 'output', $output);

        $cmd->expects($this->once())->method('clearQueueTableForStoreFallback')->with(5);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('JSON filtering not supported', $output->fetch());
    }

    public function testClearQueueForStoreFallbackDeletesMatching(): void
    {
        $adapter = $this->createMockSearchAdapter();
        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter->method('fetchAll')->willReturn([
            ['job_id' => 1, 'data' => '{"storeId":5,"foo":1}'],
            ['job_id' => 2, 'data' => '{"storeId":7}'],
            ['job_id' => 3, 'data' => '{"storeId":5}'],
        ]);

        $adapter->expects($this->once())
            ->method('delete')
            ->with('algoliasearch_queue', ['job_id IN (?)' => [1, 3]])
            ->willReturn(2);

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);

        $this->assertStringContainsString('Deleted 2 jobs for store ID 5 (fallback method)', $output->fetch());
    }

    public function testClearQueueForStoreFallbackNoMatch(): void
    {
        $adapter = $this->createStubSearchAdapter();
        $adapter->method('fetchAll')->willReturn([
            ['job_id' => 1, 'data' => '{"storeId":8}'],
        ]);

        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);

        $this->assertStringContainsString('No jobs found for store ID 5 (fallback method)', $output->fetch());
    }

    public function testClearQueueForStoreFallbackThrowsWrapped(): void
    {
        $adapter = $this->createStubSearchAdapter();
        $adapter->method('fetchAll')->willThrowException(new \Exception('db fail'));

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to clear queue for store 5: db fail');

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);
    }

    public function testMetadataStrings(): void
    {
        $cmd = $this->createObjectToTest();
        $this->assertSame('clear', $this->invokeMethod($cmd, 'getCommandName'));
        $this->assertStringContainsString('queue:', $this->invokeMethod($cmd, 'getCommandPrefix'));
        $this->assertStringContainsString('Clear the indexing queue', $this->invokeMethod($cmd, 'getCommandDescription'));
        $this->assertStringContainsString('algolia:queue:clear', $this->invokeMethod($cmd, 'getStoreArgumentDescription'));
    }

    private function createStubSearchAdapter(): AdapterInterface
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);

        return $adapter;
    }

    private function createMockSearchAdapter(): AdapterInterface&MockObject
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);

        return $adapter;
    }
}
