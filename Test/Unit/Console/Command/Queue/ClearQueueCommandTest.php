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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ClearQueueCommandTest extends TestCase
{
    private null|(State&MockObject) $state = null;
    private null|(StoreNameFetcher&MockObject) $storeNameFetcher = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(JobResourceModel&MockObject) $jobResourceModel = null;

    protected function setUp(): void
    {
        /* Load mock constructor arguments for the Command partial */
        $this->state            = $this->createMock(State::class);
        $this->storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $this->storeManager     = $this->createMock(StoreManagerInterface::class);
        $this->jobResourceModel = $this->createMock(JobResourceModel::class);
    }

    /**
     * Test that the command returns success when the user cancels the operation
     * @throws \ReflectionException
     */
    public function testExecuteReturnsSuccessWhenUserCancels(): void
    {
        $cmd = $this->makePartial(['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']);

        $cmd->expects($this->once())->method('setAreaCode');
        $cmd->expects($this->once())->method('confirmOperation')->willReturn(false);
        $cmd->expects($this->never())->method('getStoreIds');
        $cmd->expects($this->never())->method('clearQueue');

        $input  = new ArrayInput([]);
        $output = $this->bufOut();

        $code = $this->invokeExecute($cmd, $input, $output);
        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    /**
     * Test that the command clears the indexing queue for the provided store IDs
     * @throws \ReflectionException
     */
    public function testExecuteClearsProvidedStoreIds(): void
    {
        $cmd = $this->makePartial(['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']);

        $cmd->method('setAreaCode');
        $cmd->method('confirmOperation')->willReturn(true);
        $cmd->method('getStoreIds')->willReturn([1, 2]);

        $msg = 'Clearing indexing queue for stores 1, 2';
        $cmd->method('decorateOperationAnnouncementMessage')->willReturn($msg);

        $cmd->expects($this->once())
            ->method('clearQueue')
            ->with([1,2]);

        $input  = new ArrayInput([]);
        $output = $this->bufOut();

        $code = $this->invokeExecute($cmd, $input, $output);
        $this->assertSame(Cli::RETURN_SUCCESS, $code);

        $this->assertStringContainsString($msg, $output->fetch());
    }

    /**
     * Test that the command returns failure when the clear operation fails
     *
     * @throws \ReflectionException
     */
    public function testExecuteReturnsFailureOnClearException(): void
    {
        $cmd = $this->makePartial(['setAreaCode', 'confirmOperation', 'getStoreIds', 'decorateOperationAnnouncementMessage', 'clearQueue']);

        $cmd->method('setAreaCode');
        $cmd->method('confirmOperation')->willReturn(true);
        $cmd->method('getStoreIds')->willReturn([]);
        $cmd->method('decorateOperationAnnouncementMessage')->willReturn('Clearing indexing queue for all stores');

        $errMsg = "Error encountered while attempting to clear queue.";
        $cmd->method('clearQueue')->willThrowException(new \Exception($errMsg));

        $input  = new ArrayInput([]);
        $output = $this->bufOut();

        $code = $this->invokeExecute($cmd, $input, $output);
        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString($errMsg, $output->fetch());
    }

    /**
     * Test that the command calls the clearQueueForStore method for each store ID
     *
     * @throws \ReflectionException
     */
    public function testClearQueueCallsPerStore(): void
    {
        $cmd = $this->makePartial(['clearQueueForStore', 'clearQueueForAllStores']);

        $expectedStoreIds = [1, 2];
        $callIndex = 0;
        $cmd->expects($this->exactly(2))
            ->method('clearQueueForStore')
            ->willReturnCallback(function($storeId) use (&$callIndex, $expectedStoreIds) {
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

    /**
     * Test that the command calls the clearQueueForAllStores method when no store IDs are provided
     *
     * @throws \ReflectionException
     */
    public function testClearQueueEmptyCallsAllStores(): void
    {
        $cmd = $this->makePartial(['clearQueueForStore', 'clearQueueForAllStores']);

        $cmd->expects($this->never())->method('clearQueueForStore');
        $cmd->expects($this->once())->method('clearQueueForAllStores');

        $this->invokeMethod($cmd, 'clearQueue');
    }

    /**
     * Test that the command truncates the main table when no store IDs are provided
     *
     * @throws \ReflectionException
     */
    public function testClearQueueForAllStoresTruncatesMainTable(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);

        $this->jobResourceModel->method('getConnection')->willReturn($adapter);
        $this->jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $adapter->expects($this->once())->method('truncateTable')->with('algoliasearch_queue');

        $cmd = $this->makePartial();
        $this->invokeMethod($cmd, 'clearQueue');
    }

    /**
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreSuccess(): void
    {
        $cmd = $this->makePartial(['clearQueueTableForStore']);
        $output = $this->bufOut();

        // Inject output (protected on the parent); easiest is via reflection:
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->storeNameFetcher->method('getStoreName')->with(1)->willReturn('Default Store');
        $cmd->expects($this->once())->method('clearQueueTableForStore')->with(1);

        $this->invokeMethod($cmd, 'clearQueueForStore', [1]);

        $text = $output->fetch();
        $this->assertStringContainsString('Clearing indexing queue for Default Store', $text);
        $this->assertStringContainsString('Indexing queue cleared for Default Store', $text);
    }

    /**
     * @throws \ReflectionException
     * @throws NoSuchEntityException
     */
    public function testClearQueueForStoreErrorPrinted(): void
    {
        $cmd = $this->makePartial(['clearQueueTableForStore']);
        $output = $this->bufOut();
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->storeNameFetcher->method('getStoreName')->with(1)->willReturn('Default Store');
        $errorMsg = 'DB operation failed';
        $cmd->method('clearQueueTableForStore')->willThrowException(new \Exception($errorMsg));

        $this->invokeMethod($cmd, 'clearQueueForStore', [1]);

        $this->assertStringContainsString("Failed to clear indexing queue for Default Store: $errorMsg", $output->fetch());
    }

    /**
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreJsonPathDeletesJobs(): void
    {
        $adapter = $this->getMockSearchAdapter();
        $output  = $this->bufOut();

        $cmd = $this->makePartial();

        $this->setPrivateProperty($cmd, 'output', $output);

        $this->jobResourceModel->method('getConnection')->willReturn($adapter);
        $this->jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $adapter->expects($this->once())
            ->method('fetchCol')
            ->willReturn([10, 11]);

        $adapter->expects($this->once())
            ->method('delete')
            ->with('algoliasearch_queue', ['job_id IN (?)' => [10,11]])
            ->willReturn(2);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('Deleted 2 jobs for store ID 5', $output->fetch());
    }

    /**
     * @throws NoSuchEntityException
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreJsonPathNoJobs(): void
    {
        $adapter = $this->getMockSearchAdapter();
        $output  = $this->bufOut();

        $cmd = $this->makePartial();

        $this->setPrivateProperty($cmd, 'output', $output);

        $this->jobResourceModel->method('getConnection')->willReturn($adapter);
        $this->jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $adapter->method('fetchCol')->willReturn([]);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('No jobs found for store ID 5', $output->fetch());
    }

    /**
     * @throws NoSuchEntityException
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreFallsBackWhenJsonThrows(): void
    {
        $cmd = $this->makePartial(['clearQueueTableForStoreFallback']);
        $output = $this->bufOut();
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter = $this->getMockSearchAdapter();
        $this->jobResourceModel->method('getConnection')->willReturn($adapter);

        $adapter->method('select')->willThrowException(new \Exception('No JSON support'));

        $cmd->expects($this->once())->method('clearQueueTableForStoreFallback')->with(5);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('JSON filtering not supported', $output->fetch());
    }

    /**
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreFallbackDeletesMatching(): void
    {
        $cmd = $this->makePartial();
        $output = $this->bufOut();
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter = $this->getMockSearchAdapter();

        $this->jobResourceModel->method('getConnection')->willReturn($adapter);
        $this->jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $adapter->method('fetchAll')->willReturn([
            ['job_id' => 1, 'data' => '{"storeId":5,"foo":1}'],
            ['job_id' => 2, 'data' => '{"storeId":7}'],
            ['job_id' => 3, 'data' => '{"storeId":5}'],
        ]);

        $adapter->expects($this->once())
            ->method('delete')
            ->with('algoliasearch_queue', ['job_id IN (?)' => [1,3]])
            ->willReturn(2);

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);

        $this->assertStringContainsString('Deleted 2 jobs for store ID 5 (fallback method)', $output->fetch());
    }

    /**
     * @throws \ReflectionException
     */
    public function testClearQueueForStoreFallbackNoMatch(): void
    {
        $cmd = $this->makePartial();
        $output = $this->bufOut();
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter = $this->getMockSearchAdapter();
        $this->jobResourceModel->method('getConnection')->willReturn($adapter);

        $adapter->method('fetchAll')->willReturn([
            ['job_id' => 1, 'data' => '{"storeId":8}'],
        ]);

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);

        $this->assertStringContainsString('No jobs found for store ID 5 (fallback method)', $output->fetch());
    }

    public function testClearQueueForStoreFallbackThrowsWrapped(): void
    {
        $cmd = $this->makePartial();
        $adapter = $this->getMockSearchAdapter();
        $this->jobResourceModel->method('getConnection')->willReturn($adapter);

        $adapter->method('fetchAll')->willThrowException(new \Exception('db fail'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to clear queue for store 5: db fail');

        $this->invokeMethod($cmd, 'clearQueueTableForStoreFallback', [5]);
    }

    public function testMetadataStrings(): void
    {
        $cmd = $this->makePartial();
        $this->assertSame('clear', $this->invokeMethod($cmd, 'getCommandName'));
        $this->assertStringContainsString('queue:', $this->invokeMethod($cmd, 'getCommandPrefix'));
        $this->assertStringContainsString('Clear the indexing queue', $this->invokeMethod($cmd, 'getCommandDescription'));
        $this->assertStringContainsString('algolia:queue:clear', $this->invokeMethod($cmd, 'getStoreArgumentDescription'));
    }

    /**
     * Create a partial mock of the ClearQueueCommand
     *
     * @param array $methodsToMock List of methods to mock
     * @return ClearQueueCommand&MockObject
     */
    private function makePartial(array $methodsToMock = []): ClearQueueCommand&MockObject
    {
        /** @var ClearQueueCommand&MockObject $cmd */
        $cmd = $this->getMockBuilder(ClearQueueCommand::class)
            ->setConstructorArgs([
                $this->state,
                $this->storeNameFetcher,
                $this->storeManager,
                $this->jobResourceModel,
                null
            ])
            ->onlyMethods($methodsToMock)
            ->getMock();

        return $cmd;
    }

    private function getMockSearchAdapter(): AdapterInterface&MockObject
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $select  = $this->createMock(Select::class);

        // Build chainable select
        $adapter->method('select')->willReturn($select);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        return $adapter;
    }

    private function bufOut(): BufferedOutput
    {
        // Verbosity NORMAL is fine; change if you want debug output
        return new BufferedOutput();
    }

    /**
     * @throws \ReflectionException
     */
    private function invokeExecute(ClearQueueCommand $cmd, $input, $output): int
    {
        return $this->invokeMethod($cmd, 'execute', [$input, $output]);
    }

}
