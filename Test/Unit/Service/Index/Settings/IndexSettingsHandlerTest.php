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
use PHPUnit\Framework\MockObject\MockObject;

class IndexSettingsHandlerTest extends TestCase
{
    protected function createObjectToTest(
        ?AlgoliaConnector $connector = null,
        ?ConfigHelper $config = null,
        ?IndexSettingsComparator $comparator = null,
        ?IndexSettingsPreserver $preserver = null,
        ?AlgoliaLogger $logger = null,
    ): IndexSettingsHandler {
        if ($comparator === null) {
            $comparator = $this->createStub(IndexSettingsComparator::class);
            $comparator->method('matches')->willReturn(false);
        }

        if ($preserver === null) {
            $preserver = $this->createStub(IndexSettingsPreserver::class);
            // By default preservation is a pass-through so existing expectations operate on the original payload
            $preserver->method('preserve')->willReturnArgument(0);
        }

        return new IndexSettingsHandler(
            $connector ?? $this->createStub(AlgoliaConnector::class),
            $config ?? $this->createStub(ConfigHelper::class),
            $comparator,
            $preserver,
            $logger ?? $this->createStub(AlgoliaLogger::class),
        );
    }

    private function createIndexOptionsStub(int $storeId = 1): IndexOptionsInterface
    {
        $indexOptions = $this->createStub(IndexOptionsInterface::class);
        $indexOptions->method('getStoreId')->willReturn($storeId);

        return $indexOptions;
    }

    /**
     * State machine to track pending operations per store ID, so tests can assert that a
     * forwarded setSettings() call requires waitLastTask() before the store can be written again.
     */
    private function createStateMachineConnector(): AlgoliaConnector&MockObject
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn([]);

        $operationState = [];

        $connector->method('setSettings')
            ->willReturnCallback(function ($indexOptions, $settings, $forwardToReplicas, $mergeSettings = false, $mergeFrom = '') use (&$operationState) {
                $storeId = $indexOptions->getStoreId();

                if (!isset($operationState[$storeId])) {
                    $operationState[$storeId] = ['setSettingsCalled' => false, 'waitCalled' => false];
                }

                if ($operationState[$storeId]['setSettingsCalled'] && !$operationState[$storeId]['waitCalled']) {
                    throw new \RuntimeException(
                        // phpcs:ignore
                        "Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first."
                    );
                }

                $operationState[$storeId]['setSettingsCalled'] = true;
                $operationState[$storeId]['waitCalled'] = false;
            });

        $connector->method('waitLastTask')
            ->willReturnCallback(function ($storeId = null) use (&$operationState) {
                if ($storeId !== null && isset($operationState[$storeId])) {
                    $operationState[$storeId]['waitCalled'] = true;
                }
            });

        return $connector;
    }

    public function testSetSettingsWithForwardingEnabledAndMixedSettings(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        $connector = $this->createStateMachineConnector();

        $invocationCount = 0;
        $connector->expects($this->exactly(2))
            ->method('setSettings')
            ->willReturnCallback(
                function ($indexOptions, $indexSettings, $forwardToReplicas, $mergeSettings, $mergeFrom = '') use (&$invocationCount) {
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

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($this->createIndexOptionsStub($storeId), $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyExcludedSettings(): void
    {
        $storeId = 1;
        $settings = [
            'ranking' => ['asc(name)'],
            'customRanking' => ['desc(price)'],
        ];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        $indexOptions = $this->createIndexOptionsStub($storeId);

        $connector = $this->createStateMachineConnector();
        // Only one call expected (no forwarded settings since they are sorts)
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, false);

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyForwardableSettings(): void
    {
        $storeId = 1;
        $settings = [
            'attributesToHighlight' => ['title'],
            'attributesToRetrieve' => ['name'],
        ];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        $indexOptions = $this->createIndexOptionsStub($storeId);

        $connector = $this->createStateMachineConnector();
        // Only one call expected (all forwarded - no excluded settings)
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, true);

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingDisabled(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $indexOptions = $this->createIndexOptionsStub($storeId);

        $connector = $this->createStateMachineConnector();
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, false);

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testForwardSettingsWithEmptyInput(): void
    {
        $storeId = 1;
        $settings = [];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        $connector = $this->createStateMachineConnector();
        // Connector should not be called
        $connector->expects($this->never())->method('setSettings');

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($this->createIndexOptionsStub($storeId), $settings));
    }

    public function testSplitSettings(): void
    {
        $settings = [
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
            'attributesToRetrieve' => ['name'],
        ];

        $handler = $this->createObjectToTest();

        [$forward, $noForward] = $this->invokeMethod($handler, 'splitSettings', [$settings]);

        $this->assertEquals(['attributesToRetrieve' => ['name']], $forward);
        $this->assertEquals([
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
        ], $noForward);
    }

    /**
     * Ensure the state machine is working as expected by disabling replica forwarding
     * and explicitly invoking subsequent setSettings operations
     */
    public function testSubsequentSetSettingsWithoutWaitThrowsException(): void
    {
        $storeId = 1;
        $settings = ['attributesToRetrieve' => ['name']];

        $config = $this->createStub(ConfigHelper::class);
        // Disable forwarding for explicit test
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $connector = $this->createStateMachineConnector();
        // setSettings() is preceded by exactly one getSettings() call per handler->setSettings()
        // invocation, so this is called exactly twice across the two attempts below.
        $connector->expects($this->exactly(2))->method('getSettings');

        $handler = $this->createObjectToTest($connector, $config);
        $indexOptions = $this->createIndexOptionsStub($storeId);

        // First call should succeed
        $this->assertTrue($handler->setSettings($indexOptions, $settings));

        // Second call without wait should throw exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    /**
     * Explicitly test the state machine succeeds by disabling replica forwarding
     * and explicitly invoking the wait operation
     * */
    public function testSubsequentSetSettingsAfterWaitSucceeds(): void
    {
        $storeId = 1;
        $settings = ['attributesToRetrieve' => ['name']];

        $config = $this->createStub(ConfigHelper::class);
        // Disable forwarding for explicit test
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $connector = $this->createStateMachineConnector();
        $connector->expects($this->exactly(2))->method('setSettings');
        $connector->expects($this->once())->method('waitLastTask')->with($storeId);

        $handler = $this->createObjectToTest($connector, $config);
        $indexOptions = $this->createIndexOptionsStub($storeId);

        // First call should succeed
        $handler->setSettings($indexOptions, $settings);

        // Wait for the task
        $connector->waitLastTask($storeId);

        // Second call after wait should succeed
        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testDifferentStoreIdsDontInterfere(): void
    {
        $storeId1 = 1;
        $storeId2 = 2;
        $settings = ['attributesToRetrieve' => ['name']];

        $indexOptions1 = $this->createIndexOptionsStub($storeId1);
        $indexOptions2 = $this->createIndexOptionsStub($storeId2);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $connector = $this->createStateMachineConnector();
        // Both calls should succeed as they use different store IDs
        $connector->expects($this->exactly(2))->method('setSettings');

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue($handler->setSettings($indexOptions1, $settings));
        $this->assertTrue($handler->setSettings($indexOptions2, $settings));
    }

    /**
     *  Replica forwarding should abstract the wait operation internally
     *  However require caller to invoke wait for subsequent ops
     *  This is *by design* to minimize unnecessary IO blocking
     *  This test ensures this logic stays in place
     */
    public function testForwardingEnabledMultipleCallsRequireWait(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name'],
        ];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        $connector = $this->createStateMachineConnector();
        // 2 internal calls + 1 explicit call
        $connector->expects($this->exactly(3))->method('setSettings');

        $handler = $this->createObjectToTest($connector, $config);
        $indexOptions = $this->createIndexOptionsStub($storeId);

        // First call makes two internal setSettings calls
        $handler->setSettings($indexOptions, $settings);

        // Second call to handler should fail because no wait was called
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testSkippedSetSettings(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name'],
        ];

        $comparator = $this->createStub(IndexSettingsComparator::class);
        $comparator->method('matches')->willReturn(true);

        // matches() = true means setSettings() is never reached, so a plain stub is enough here.
        $connector = $this->createStub(AlgoliaConnector::class);

        $handler = $this->createObjectToTest($connector, comparator: $comparator);

        $this->assertFalse($handler->setSettings($this->createIndexOptionsStub($storeId), $settings));
    }

    public function testGetSettingsCalledExactlyOncePerSetSettings(): void
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('getSettings')
            ->willReturn(['attributesForFaceting' => ['categories']]);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $handler = $this->createObjectToTest($connector, $config);

        $this->assertTrue(
            $handler->setSettings($this->createIndexOptionsStub(), ['attributesForFaceting' => ['categories']])
        );
    }

    public function testPreserverInvokedBeforeComparator(): void
    {
        $order = [];

        $connector = $this->createMock(AlgoliaConnector::class);
        // preserve() and matches() must both see the remote settings fetched exactly once.
        $connector->expects($this->once())->method('getSettings')->willReturn(['attributesForFaceting' => ['categories']]);

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

        $config = $this->createStub(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = $this->createObjectToTest($connector, $config, $comparator, $preserver);

        $handler->setSettings($this->createIndexOptionsStub(), ['attributesForFaceting' => ['categories']]);

        $this->assertSame(['preserve', 'matches'], $order);
    }

    public function testComparatorReceivesPreFetchedRemote(): void
    {
        $remote = ['attributesForFaceting' => ['categories', '_collections']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $indexOptions = $this->createIndexOptionsStub();

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())
            ->method('matches')
            ->with($indexOptions, $this->anything(), $remote)
            ->willReturn(true);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = $this->createObjectToTest($connector, $config, $comparator);

        $this->assertFalse($handler->setSettings($indexOptions, ['attributesForFaceting' => ['categories']]));
    }

    public function testNoOpDetectionAccountsForPreservedEntries(): void
    {
        $proposed = ['attributesForFaceting' => ['a', 'b']];
        $remote = ['attributesForFaceting' => ['a', 'b', '_x']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn($remote);
        // The only diff was the preserved entry, so no write should be issued.
        $connector->expects($this->never())->method('setSettings');

        $preserver = $this->createStub(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturn(['attributesForFaceting' => ['a', 'b', '_x']]);

        // Use the real comparator so the no-op detection is genuinely exercised.
        $comparator = new IndexSettingsComparator($connector);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = $this->createObjectToTest($connector, $config, $comparator, $preserver);

        $this->assertFalse($handler->setSettings($this->createIndexOptionsStub(), $proposed));
    }
}
