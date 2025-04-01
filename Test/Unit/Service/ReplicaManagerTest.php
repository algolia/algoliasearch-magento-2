<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
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
        $algoliaHelper = $this->createMock(AlgoliaHelper::class);
        $replicaState = $this->createMock(ReplicaState::class);
        $virtualReplicaValidatorFactory = $this->createMock(VirtualReplicaValidatorFactory::class);
        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(DiagnosticsLogger::class);

        $this->replicaManager = new ReplicaManagerTestable(
            $configHelper,
            $algoliaHelper,
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

        $this->assertNotContains($replicaToRemove, $newReplicas);
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
    }
}
