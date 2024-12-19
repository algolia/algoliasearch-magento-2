<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product\Traits;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Store\Api\Data\StoreInterface;

trait ReplicaAssertionsTrait
{
    protected function assertSortToReplicaConfigParity(
        string $primaryIndexName,
        array $sorting,
        array $replicas,
        ?int $storeId = null
    ): void
    {
        foreach ($sorting as $sortAttr) {
            $replicaIndexName = $sortAttr['name'];
            $isVirtual = array_key_exists('virtualReplica', $sortAttr) && $sortAttr['virtualReplica'];
            $needle = $isVirtual
                ? "virtual($replicaIndexName)"
                : $replicaIndexName;
            $this->assertContains($needle, $replicas);

            $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName, $storeId);
            $sort = reset($sortAttr['ranking']);
            if ($isVirtual) {
                $this->assertVirtualReplicaRanking($replicaSettings, $sort);
            } else {
                $this->assertStandardReplicaRanking($replicaSettings, $sort);
            }
        }
    }

    protected function assertReplicaIndexExists(
        string $primaryIndexName,
        string $replicaIndexName,
        ?int $storeId = null
    ): array
    {
        $replicaSettings = $this->algoliaHelper->getSettings($replicaIndexName, $storeId);
        $this->assertArrayHasKey('primary', $replicaSettings);
        $this->assertEquals($primaryIndexName, $replicaSettings['primary']);
        return $replicaSettings;
    }

    protected function assertReplicaRanking(array $replicaSettings, string $rankingKey, string $sort) {
        $this->assertArrayHasKey($rankingKey, $replicaSettings);
        $this->assertEquals($sort, reset($replicaSettings[$rankingKey]));
    }

    protected function assertStandardReplicaRanking(array $replicaSettings, string $sort): void
    {
        $this->assertReplicaRanking($replicaSettings, 'ranking', $sort);
    }

    protected function assertVirtualReplicaRanking(array $replicaSettings, string $sort): void
    {
        $this->assertReplicaRanking($replicaSettings, 'customRanking', $sort);
    }

    protected function assertStandardReplicaRankingOld(array $replicaSettings, string $sortAttr, string $sortDir): void
    {
        $this->assertArrayHasKey('ranking', $replicaSettings);
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['ranking']));
    }

    protected function assertVirtualReplicaRankingOld(array $replicaSettings, string $sortAttr, string $sortDir): void
    {
        $this->assertArrayHasKey('customRanking', $replicaSettings);
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['customRanking']));
    }

    /**
     * @param string[] $replicaSetting
     * @param string $replicaIndexName
     * @return bool
     */
    protected function isVirtualReplica(array $replicaSetting, string $replicaIndexName): bool
    {
        return (bool) array_filter(
            $replicaSetting,
            function ($replica) use ($replicaIndexName) {
                return str_contains($replica, "virtual($replicaIndexName)");
            }
        );
    }

    protected function isStandardReplica(array $replicaSetting, string $replicaIndexName): bool
    {
        return (bool) array_filter(
            $replicaSetting,
            function ($replica) use ($replicaIndexName) {
                $regex = '/^' . preg_quote($replicaIndexName) . '$/';
                return preg_match($regex, $replica);
            }
        );
    }

    protected function hasSortingAttribute($sortAttr, $sortDir): bool
    {
        $sorting = $this->configHelper->getSorting();
        return (bool) array_filter(
            $sorting,
            function($sort) use ($sortAttr, $sortDir) {
                return $sort['attribute'] == $sortAttr
                    && $sort['sort'] == $sortDir;
            }
        );
    }

    protected function assertSortingAttribute($sortAttr, $sortDir): void
    {
        $this->assertTrue($this->hasSortingAttribute($sortAttr, $sortDir));
    }

    protected function assertNoSortingAttribute($sortAttr, $sortDir): void
    {
        $this->assertFalse($this->hasSortingAttribute($sortAttr, $sortDir));
    }

    /**
     * ConfigHelper::setSorting uses WriterInterface which does not update unless DB isolation is disabled
     * This provides a workaround to test using MutableScopeConfigInterface with DB isolation enabled
     */
    protected function mockSortUpdate(string $sortAttr, string $sortDir, array $attr, ?StoreInterface $store = null): void
    {
        $sorting = $this->configHelper->getSorting(!is_null($store) ? $store->getId() : null);
        $existing = array_filter($sorting, function ($item) use ($sortAttr, $sortDir) {
            return $item['attribute'] === $sortAttr && $item['sort'] === $sortDir;
        });


        if ($existing) {
            $idx = array_key_first($existing);
            $sorting[$idx] = array_merge($existing[$idx], $attr);
        }
        else {
            $sorting[] = array_merge(
                [
                    'attribute' => $sortAttr,
                    'sort'       => $sortDir,
                    'sortLabel'  => $sortAttr
                ],
                $attr
            );
        }
        $this->setConfig(
            ConfigHelper::SORTING_INDICES,
            json_encode($sorting),
            !is_null($store) ? $store->getCode() : 'default'
        );
    }
}
