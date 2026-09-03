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
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testSetSettingsWithForwardingEnabledAndMixedSettings(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $config = $this->createMock(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->with($storeId)->willReturn(true);

        $invocationCount = 0;
        $connector = $this->createMock(AlgoliaConnector::class);
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

        // IndexSettingsComparator::matches() is called 3 times:
        // - once for the full payload
        // - once for $forward
        // - once for $noforward
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->exactly(3))->method('matches');
        // Only two taskIDs are collected ($forward and $noforward)
        $connector->expects($this->exactly(2))->method('collectTaskIdToWaitFor');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

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

        // Only one call expected (no forwarded settings since they are sorts)
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, false);

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload
        // - once for $noforward
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->exactly(2))->method('matches');
        // Only one taskID is collected ($noforward)
        $connector->expects($this->once())->method('collectTaskIdToWaitFor');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

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

        // Only one call expected (all forwarded - no excluded settings)
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, true);

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload
        // - once for $forward
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->exactly(2))->method('matches');
        // Only one taskID is collected ($forward)
        $connector->expects($this->exactly(1))->method('collectTaskIdToWaitFor');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

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

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('setSettings')
            ->with($indexOptions, $settings, false);

        // IndexSettingsComparator::matches() is called once (since we don't split)
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())->method('matches');
        // Only one taskID is collected (full payload with early return)
        $connector->expects($this->once())->method('collectTaskIdToWaitFor');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

        $this->assertTrue($handler->setSettings($indexOptions, $settings));
    }

    public function testForwardSettingsWithEmptyInput(): void
    {
        $storeId = 1;
        $settings = [];

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(true);

        // Connector should not be called
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->never())->method('setSettings');
        $connector->expects($this->never())->method('collectTaskIdToWaitFor');

        // IndexSettingsComparator::matches() is called once (empty array will always match and early return)
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())->method('matches');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

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

    public function testDifferentStoreIdsDontInterfere(): void
    {
        $storeId1 = 1;
        $storeId2 = 2;
        $settings = ['attributesToRetrieve' => ['name']];

        $indexOptions1 = $this->createIndexOptionsStub($storeId1);
        $indexOptions2 = $this->createIndexOptionsStub($storeId2);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        // Both calls should succeed as they use different store IDs
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->exactly(2))->method('setSettings');
        $connector->expects($this->exactly(2))->method('collectTaskIdToWaitFor');

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload of each of the 2 stores
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->exactly(2))->method('matches');

        $handler = $this->createObjectToTest($connector, $config, $comparator);

        $this->assertTrue($handler->setSettings($indexOptions1, $settings));
        $this->assertTrue($handler->setSettings($indexOptions2, $settings));
    }

    public function testSkippedSetSettings(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name'],
        ];

        // IndexSettingsComparator::matches() is called once (match = early return)
        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())->method('matches')->willReturn(true);

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->never())->method('collectTaskIdToWaitFor');

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

        $connector = $this->createStub(AlgoliaConnector::class);
        // preserve() and matches() must both see the remote settings fetched exactly once.
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

    #[DataProvider('settingsProvider')]
    public function testForwardAndNoForwardSettingsChanges(
        array $proposed,
        array $remote,
        bool $forwardToReplicas,
        int $expectedNumberOfTasksCollected
    ): void {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn($remote);
        $connector->expects($this->exactly($expectedNumberOfTasksCollected))->method('setSettings');
        $connector->expects($this->exactly($expectedNumberOfTasksCollected))->method('collectTaskIdToWaitFor');

        $preserver = $this->createStub(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturn($proposed);

        // Use the real comparator so the forward/no-forward split is genuinely exercised.
        $comparator = new IndexSettingsComparator($connector);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn($forwardToReplicas);

        $handler = $this->createObjectToTest($connector, $config, $comparator, $preserver);

        $handler->setSettings($this->createIndexOptionsStub(), $proposed);
    }

    public static function settingsProvider(): array
    {
        return [
            [ // Both forward and noforward have changes => 2 collected tasks expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 2
            ],
            [ // Only forward has changes  => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 1
            ],
            [ // Only noforward has changes => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 1
            ],
            [ // forward and noforward are identical => 0 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 0
            ],
            [ // Both forward and noforward have changes but forward to replicas is set to false => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => false,
                'expectedNumberOfTasksCollected' => 1
            ],
        ];
    }
}
