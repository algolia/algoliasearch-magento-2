<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Validator\VirtualReplicaValidatorFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ReplicaManagerTest extends TestCase
{
    protected ?ReplicaManager $replicaManager;

    public function setUp(): void
    {
        $configHelper = $this->createMock(ConfigHelper::class);
        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $indexOptionsBuilder = $this->createMock(IndexOptionsBuilder::class);
        $replicaState = $this->createMock(ReplicaState::class);
        $virtualReplicaValidatorFactory = $this->createMock(VirtualReplicaValidatorFactory::class);
        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(DiagnosticsLogger::class);

        $this->replicaManager = new ReplicaManagerTestable(
            $configHelper,
            $algoliaConnector,
            $indexOptionsBuilder,
            $replicaState,
            $virtualReplicaValidatorFactory,
            $indexNameFetcher,
            $storeNameFetcher,
            $sortingTransformer,
            $storeManager,
            $logger
        );
    }

    public function testVirtualReplicaSettingRemove(): void
    {
        $replicaSetting = [
            'virtual(replica1)',
            'virtual(replica2)',
            'virtual(replica3)'
        ];
        $replicaToRemove = 'replica2';

        $newReplicas = $this->replicaManager->removeReplicaFromReplicaSetting($replicaSetting, $replicaToRemove);

        $this->assertNotContains("virtual($replicaToRemove)", $newReplicas);
        $this->assertContains('virtual(replica1)', $newReplicas);
        $this->assertContains('virtual(replica3)', $newReplicas);
    }

    public function testStandardReplicaSettingRemove(): void
    {
        $replicaSetting = [
            'replica1',
            'replica2',
            'replica3'
        ];
        $replicaToRemove = 'replica2';

        $newReplicas = $this->replicaManager->removeReplicaFromReplicaSetting($replicaSetting, $replicaToRemove);

        $this->assertNotContains($replicaToRemove, $newReplicas);
        $this->assertContains('replica1', $newReplicas);
        $this->assertContains('replica3', $newReplicas);

    }
}
