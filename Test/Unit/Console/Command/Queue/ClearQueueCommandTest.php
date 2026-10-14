<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ClearQueueCommand;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;
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
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreName')->with(1)->willReturn('Default Store');

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
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreName')->with(1)->willReturn('Default Store');

        $cmd = $this->createPartialObjectToTest(['clearQueueTableForStore'], storeNameFetcher: $storeNameFetcher);
        $output = new BufferedOutput();
        $this->setPrivateProperty($cmd, 'output', $output);

        $errorMsg = 'DB operation failed';
        $cmd->expects($this->once())->method('clearQueueTableForStore')->willThrowException(new \Exception($errorMsg));

        $this->invokeMethod($cmd, 'clearQueueForStore', [1]);

        $this->assertStringContainsString("Failed to clear indexing queue for Default Store: $errorMsg", $output->fetch());
    }

    public function testClearQueueForStoreDeletesJobsByStoreIdColumn(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $adapter->expects($this->once())
            ->method('delete')
            ->with('algoliasearch_queue', ['store_id = ?' => 5])
            ->willReturn(2);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('Deleted 2 jobs for store ID 5', $output->fetch());
    }

    public function testClearQueueForStoreNoJobs(): void
    {
        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('delete')->willReturn(0);

        $output = new BufferedOutput();

        $jobResourceModel = $this->createStub(JobResourceModel::class);
        $jobResourceModel->method('getConnection')->willReturn($adapter);
        $jobResourceModel->method('getMainTable')->willReturn('algoliasearch_queue');

        $cmd = $this->createObjectToTest(jobResourceModel: $jobResourceModel);
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->invokeMethod($cmd, 'clearQueueForStore', [5]);

        $this->assertStringContainsString('No jobs found for store ID 5', $output->fetch());
    }

    public function testMetadataStrings(): void
    {
        $cmd = $this->createObjectToTest();
        $this->assertSame('clear', $this->invokeMethod($cmd, 'getCommandName'));
        $this->assertStringContainsString('queue:', $this->invokeMethod($cmd, 'getCommandPrefix'));
        $this->assertStringContainsString('Clear the indexing queue', $this->invokeMethod($cmd, 'getCommandDescription'));
        $this->assertStringContainsString('algolia:queue:clear', $this->invokeMethod($cmd, 'getStoreArgumentDescription'));
    }
}
