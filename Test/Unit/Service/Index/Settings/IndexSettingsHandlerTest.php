<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index\Settings;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsPreserver;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class IndexSettingsHandlerTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;

    protected ?ConfigHelper $config = null;
    protected ?IndexSettingsComparator $indexSettingsComparator = null;
    protected ?IndexSettingsPreserver $indexSettingsPreserver = null;
    protected ?AlgoliaLogger $logger = null;

    protected ?IndexOptionsInterface $indexOptions = null;

    private ?IndexSettingsHandler $handler = null;

    /**
     * State machine to track pending operations per store ID
     * Format: [storeId => ['totalCalls' => int, 'waitCalled' => bool, 'batchesCompleted' => int]]
     */
    private array $operationState = [];

    protected function setUp(): void
    {
        $this->connector = $this->createMock(AlgoliaConnector::class);
        $this->config = $this->createMock(ConfigHelper::class);
        $this->indexSettingsComparator = $this->createMock(IndexSettingsComparator::class);
        $this->indexSettingsComparator->method('matches')->willReturn(false);
        $this->indexSettingsPreserver = $this->createMock(IndexSettingsPreserver::class);
        // By default preservation is a pass-through so existing expectations operate on the original payload
        $this->indexSettingsPreserver->method('preserve')->willReturnArgument(0);
        $this->logger = $this->createMock(AlgoliaLogger::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);

        // Configure the mock to use our state machine
        $this->setupStateMachineMock();

        $this->handler = new IndexSettingsHandler(
            $this->connector,
            $this->config,
            $this->indexSettingsComparator,
            $this->indexSettingsPreserver,
            $this->logger,
        );
    }

    private function setupStateMachineMock(): void
    {
        $this->connector->method('setSettings')
            ->willReturnCallback(function($indexOptions, $settings, $forwardToReplicas, $mergeSettings, $mergeFrom = '') {
                $storeId = $indexOptions->getStoreId();

                // Initialize state if not exists
                if (!isset($this->operationState[$storeId])) {
                    $this->operationState[$storeId] = [
                        'setSettingsCalled' => false,
                        'waitCalled' => false,
                    ];
                }

//                // Check that setSettings is not stacked for this $storeId
//                if ($this->operationState[$storeId]['setSettingsCalled'] &&
//                    !$this->operationState[$storeId]['waitCalled']) {
//                    throw new \RuntimeException(
//                        // phpcs:ignore
//                        "Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first."
//                    );
//                }

                // Update state
                $this->operationState[$storeId]['setSettingsCalled'] = true;
                $this->operationState[$storeId]['waitCalled'] = false;
            });

        $this->connector->method('waitLastTask')
            ->willReturnCallback(function($storeId = null) {
                if ($storeId !== null && isset($this->operationState[$storeId])) {
                    $this->operationState[$storeId]['waitCalled'] = true;
                }
            });
    }

    private function resetOperationState(): void
    {
        $this->operationState = [];
    }

    public function testSetSettingsWithForwardingEnabledAndMixedSettings(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->with($storeId)
            ->willReturn(true);

        $invocationCount = 0;
        $this->connector->expects($this->exactly(2))
            ->method('setSettings')
            ->willReturnCallback(
                function($indexOptions, $indexSettings, $forwardToReplicas, $mergeSettings, $mergeFrom = '') use (&$invocationCount) {
                    $invocationCount++;

                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(['attributesToRetrieve' => ['name', 'price']], $indexSettings);
                            $this->assertTrue($forwardToReplicas);
                            $this->assertFalse($mergeSettings);

                            break;
                        case 2:
                            $this->assertEquals(['customRanking' => ['desc(price)']], $indexSettings);
                            $this->assertFalse($forwardToReplicas);
                            $this->assertFalse($mergeSettings);

                            break;
                }
            }
            );

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyExcludedSettings(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = [
            'ranking' => ['asc(name)'],
            'customRanking' => ['desc(price)'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Only one call expected (no forwarded settings since they are sorts)
        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                false
            );

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyForwardableSettings(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = [
            'attributesToHighlight' => ['title'],
            'attributesToRetrieve' => ['name'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Only one call expected (all forwarded - no excluded settings)
        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                true
            );

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingDisabled(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                false
            );

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testForwardSettingsWithEmptyInput(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = [];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Connector should not be called
        $this->connector->expects($this->never())->method('setSettings');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }


    public function testSplitSettings(): void
    {
        $settings = [
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
            'attributesToRetrieve' => ['name'],
        ];

        [$forward, $noForward] = $this->invokeMethod($this->handler, 'splitSettings', [$settings]);

        $this->assertEquals(['attributesToRetrieve' => ['name']], $forward);
        $this->assertEquals([
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
        ], $noForward);
    }

//    /**
//     * Ensure the state machine is working as expected by disabling replica forwarding
//     * and explicitly invoking subsequent setSettings operations
//     */
//    public function testSubsequentSetSettingsWithoutWaitThrowsException(): void
//    {
//        $this->resetOperationState();
//
//        $storeId = 1;
//        $settings = ['attributesToRetrieve' => ['name']];
//
//        $this->indexOptions->method('getStoreId')->willReturn($storeId);
//
//        // Disable forwarding for explicit test
//        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
//            ->willReturn(false);
//
//        // First call should succeed
//        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
//
//        // Second call without wait should throw exception
//        $this->expectException(\RuntimeException::class);
//        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");
//
//        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
//    }

    /**
     * Explicitly test the state machine succeeds by disabling replica forwarding
     * and explicitly invoking the wait operation
     * */
    public function testSubsequentSetSettingsAfterWaitSucceeds(): void
    {
        $this->resetOperationState();

        $storeId = 1;
        $settings = ['attributesToRetrieve' => ['name']];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        // Disable forwarding for explicit test
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        $this->connector->expects($this->exactly(2))
            ->method('setSettings');

        $this->connector->expects($this->once())
            ->method('waitLastTask')
            ->with($storeId);

        // First call should succeed
        $this->handler->setSettings($this->indexOptions, $settings);

        // Wait for the task
        $this->connector->waitLastTask($storeId);

        // Second call after wait should succeed
        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testDifferentStoreIdsDontInterfere(): void
    {
        $this->resetOperationState();

        $storeId1 = 1;
        $storeId2 = 2;
        $settings = ['attributesToRetrieve' => ['name']];

        $indexOptions1 = $this->createMock(IndexOptionsInterface::class);
        $indexOptions1->method('getStoreId')->willReturn($storeId1);

        $indexOptions2 = $this->createMock(IndexOptionsInterface::class);
        $indexOptions2->method('getStoreId')->willReturn($storeId2);

        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        // Both calls should succeed as they use different store IDs
        $this->connector->expects($this->exactly(2))
            ->method('setSettings');

        $this->assertTrue($this->handler->setSettings($indexOptions1, $settings));
        $this->assertTrue($this->handler->setSettings($indexOptions2, $settings));
    }

//    /**
//     *  Replica forwarding should abstract the wait operation internally
//     *  However require caller to invoke wait for subsequent ops
//     *  This is *by design* to minimize unnecessary IO blocking
//     *  This test ensures this logic stays in place
//     */
//    public function testForwardingEnabledMultipleCallsRequireWait(): void
//    {
//        $this->resetOperationState();
//
//        $storeId = 1;
//        $settings = [
//            'customRanking' => ['desc(price)'],
//            'attributesToRetrieve' => ['name'],
//        ];
//
//        $this->indexOptions->method('getStoreId')->willReturn($storeId);
//        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
//            ->willReturn(true);
//
//        // 2 internal calls + 1 explicit call
//        $this->connector->expects($this->exactly(3))
//            ->method('setSettings');
//
//        // First call makes two internal setSettings calls
//        $this->handler->setSettings($this->indexOptions, $settings);
//
//        // Second call to handler should fail because no wait was called
//        $this->expectException(\RuntimeException::class);
//        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");
//
//        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
//    }

    public function testSkippedSetSettings(): void
    {
        $this->resetOperationState();

        $indexSettingsComparator = $this->createMock(IndexSettingsComparator::class);
        $indexSettingsComparator->method('matches')->willReturn(true);

        $this->handler = new IndexSettingsHandler(
            $this->connector,
            $this->config,
            $indexSettingsComparator,
            $this->indexSettingsPreserver,
            $this->logger,
        );

        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);

        $this->assertFalse($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testGetSettingsCalledExactlyOncePerSetSettings(): void
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('getSettings')
            ->willReturn(['attributesForFaceting' => ['categories']]);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturnArgument(0);

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->method('matches')->willReturn(false);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertTrue($handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]));
    }

    public function testPreserverInvokedBeforeComparator(): void
    {
        $order = [];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn(['attributesForFaceting' => ['categories']]);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->expects($this->once())
            ->method('preserve')
            ->willReturnCallback(function ($proposed) use (&$order) {
                $order[] = 'preserve';

                return $proposed;
            });

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())
            ->method('matches')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'matches';

                return true;
            });

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]);

        $this->assertSame(['preserve', 'matches'], $order);
    }

    public function testComparatorReceivesPreFetchedRemote(): void
    {
        $remote = ['attributesForFaceting' => ['categories', '_collections']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturnArgument(0);

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())
            ->method('matches')
            ->with($this->indexOptions, $this->anything(), $remote)
            ->willReturn(true);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertFalse($handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]));
    }

    public function testNoOpDetectionAccountsForPreservedEntries(): void
    {
        $proposed = ['attributesForFaceting' => ['a', 'b']];
        $remote = ['attributesForFaceting' => ['a', 'b', '_x']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn($remote);
        // The only diff was the preserved entry, so no write should be issued.
        $connector->expects($this->never())->method('setSettings');

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturn(['attributesForFaceting' => ['a', 'b', '_x']]);

        // Use the real comparator so the no-op detection is genuinely exercised.
        $comparator = new IndexSettingsComparator($connector);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertFalse($handler->setSettings($this->indexOptions, $proposed));
    }
}
