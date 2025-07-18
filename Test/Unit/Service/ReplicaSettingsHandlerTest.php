<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\ReplicaSettingsHandler;
use PHPUnit\Framework\TestCase;

class ReplicaSettingsHandlerTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;

    protected ?ConfigHelper $config = null;

    protected ?IndexOptionsInterface $indexOptions = null;

    private ?ReplicaSettingsHandler $handler = null;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(AlgoliaConnector::class);
        $this->config = $this->createMock(ConfigHelper::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);

        $this->handler = new ReplicaSettingsHandlerTestable($this->connector, $this->config);
    }

    public function testSetSettingsWithForwardingEnabledAndMixedSettings(): void
    {
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
                function($indexOptions, $indexSettings, $forwardToReplicas, $mergeSettings, $mergeFrom) use (&$invocationCount) {
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
    }

    public function testSetSettingsWithForwardingEnabledOnlyExcludedSettings(): void
    {
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
    }

    public function testSetSettingsWithForwardingEnabledOnlyForwardableSettings(): void
    {
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
    }

    public function testSetSettingsWithForwardingDisabled(): void
    {
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
    }

    public function testForwardSettingsWithEmptyInput(): void
    {
        $storeId = 1;
        $settings = [];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Connector should not be called
        $this->connector->expects($this->never())->method('setSettings');

        $this->handler->setSettings($this->indexOptions, $settings);
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
}
