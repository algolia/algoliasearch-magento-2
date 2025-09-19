<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexSettingsHandler;
use PHPUnit\Framework\TestCase;

class IndexSettingsHandlerTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;

    protected ?ConfigHelper $config = null;

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
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);

        // Configure the mock to use our state machine
        $this->setupStateMachineMock();

        $this->handler = new IndexSettingsHandlerTestable($this->connector, $this->config);
    }

    private function setupStateMachineMock(): void
    {
        $this->connector->method('setSettings')
            ->willReturnCallback(function($indexOptions, $settings, $forwardToReplicas, $mergeSettings, $mergeFrom = '') {
                $storeId = $indexOptions->getStoreId();
                
                // Initialize state if not exists
                if (!isset($this->operationState[$storeId])) {
                    $this->operationState[$storeId] = [
                        'totalCalls' => 0,
                        'waitCalled' => false,
                        'batchesCompleted' => 0
                    ];
                }
                
                // Check if we have completed batches that haven't been waited for
                if ($this->operationState[$storeId]['batchesCompleted'] > 0 && 
                    !$this->operationState[$storeId]['waitCalled']) {
                    throw new \RuntimeException(
                        "Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first."
                    );
                }
                
                // Increment call count
                $this->operationState[$storeId]['totalCalls']++;
                $this->operationState[$storeId]['waitCalled'] = false;
            });

        $this->connector->method('waitLastTask')
            ->willReturnCallback(function($storeId = null) {
                if ($storeId !== null && isset($this->operationState[$storeId])) {
                    // Mark that wait has been called
                    $this->operationState[$storeId]['waitCalled'] = true;
                }
            });
    }

    /**
     * Call this after IndexSettingsHandler.setSettings() completes to mark the batch as done
     */
    private function markBatchCompleted(int $storeId): void
    {
        if (isset($this->operationState[$storeId])) {
            $this->operationState[$storeId]['batchesCompleted']++;
        }
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
            'attributesToRetrieve' => ['name', 'price']
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
                            $this->assertTrue($mergeSettings);
                            break;
                }
            });

        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
    }

    public function testSetSettingsWithForwardingEnabledOnlyExcludedSettings(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = [
            'ranking' => ['asc(name)'],
            'customRanking' => ['desc(price)']
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
                false,
                true,
                ''
            );

        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
    }

    public function testSetSettingsWithForwardingEnabledOnlyForwardableSettings(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = [
            'attributesToHighlight' => ['title'],
            'attributesToRetrieve' => ['name']
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
                true,
                false
            );

        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
    }

    public function testSetSettingsWithForwardingDisabled(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price']
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                false,
                true,
                ''
            );

        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
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

        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
    }


    public function testSplitSettings(): void
    {
        $settings = [
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
            'attributesToRetrieve' => ['name']
        ];

        [$forward, $noForward] = $this->handler->splitSettings($settings);

        $this->assertEquals(['attributesToRetrieve' => ['name']], $forward);
        $this->assertEquals([
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)']
        ], $noForward);
    }

    public function testSubsequentSetSettingsWithoutWaitThrowsException(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = ['attributesToRetrieve' => ['name']];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        // First call should succeed
        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
        
        // Second call without wait should throw exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");
        
        $this->handler->setSettings($this->indexOptions, $settings);
    }

    public function testSubsequentSetSettingsAfterWaitSucceeds(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = ['attributesToRetrieve' => ['name']];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        // Configure connector to expect two setSettings calls and one waitLastTask call
        $this->connector->expects($this->exactly(2))
            ->method('setSettings');
        
        $this->connector->expects($this->once())
            ->method('waitLastTask')
            ->with($storeId);

        // First call should succeed
        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
        
        // Wait for the task
        $this->connector->waitLastTask($storeId);
        
        // Second call after wait should succeed
        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
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

        $this->handler->setSettings($indexOptions1, $settings);
        $this->markBatchCompleted($storeId1);
        $this->handler->setSettings($indexOptions2, $settings);
        $this->markBatchCompleted($storeId2);
    }

    public function testForwardingEnabledMultipleCallsRequireWait(): void
    {
        $this->resetOperationState();
        
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name']
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // First call makes two internal setSettings calls
        $this->handler->setSettings($this->indexOptions, $settings);
        $this->markBatchCompleted($storeId);
        
        // Second call should fail because no wait was called
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot call setSettings on store $storeId: previous operation still pending. Call waitLastTask first.");
        
        $this->handler->setSettings($this->indexOptions, $settings);
    }
}
