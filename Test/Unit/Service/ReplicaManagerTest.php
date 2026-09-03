<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\AlgoliaSearch\Validator\VirtualReplicaValidatorFactory;
use Magento\Store\Model\StoreManagerInterface;
use ReflectionException;

class ReplicaManagerTest extends TestCase
{
    protected function createObjectToTest(?ConfigHelper $configHelper = null): ReplicaManager
    {
        return new ReplicaManager(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $this->createStub(InstantSearchHelper::class),
            $this->createStub(AlgoliaConnector::class),
            $this->createStub(IndexOptionsBuilder::class),
            $this->createStub(ReplicaState::class),
            $this->createStub(VirtualReplicaValidatorFactory::class),
            $this->createStub(IndexNameFetcher::class),
            $this->createStub(StoreNameFetcher::class),
            $this->createStub(SortingTransformer::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(DiagnosticsLogger::class),
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testVirtualReplicaSettingRemove(): void
    {
        $replicaSetting = [
            'virtual(replica1)',
            'virtual(replica2)',
            'virtual(replica3)',
        ];
        $replicaToRemove = 'replica2';

        $newReplicas = $this->invokeMethod(
            $this->createObjectToTest(),
            'removeReplicaFromReplicaSetting',
            [$replicaSetting, $replicaToRemove]
        );

        $this->assertNotContains("virtual($replicaToRemove)", $newReplicas);
        $this->assertContains('virtual(replica1)', $newReplicas);
        $this->assertContains('virtual(replica3)', $newReplicas);
    }

    /**
     * @throws ReflectionException
     */
    public function testStandardReplicaSettingRemove(): void
    {
        $replicaSetting = [
            'replica1',
            'replica2',
            'replica3',
        ];
        $replicaToRemove = 'replica2';

        $newReplicas = $this->invokeMethod(
            $this->createObjectToTest(),
            'removeReplicaFromReplicaSetting',
            [$replicaSetting, $replicaToRemove]
        );

        $this->assertNotContains($replicaToRemove, $newReplicas);
        $this->assertContains('replica1', $newReplicas);
        $this->assertContains('replica3', $newReplicas);
    }

    public function testMaxReplicasLimit(): void
    {
        $replicaManager = $this->createObjectToTest();
        $this->assertEquals(20, $replicaManager->getMaxVirtualReplicasPerIndex());

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getMaxReplicasLimit')->willReturn(10);

        $replicaManager = $this->createObjectToTest($configHelper);
        $this->assertEquals(10, $replicaManager->getMaxVirtualReplicasPerIndex());
    }
}
