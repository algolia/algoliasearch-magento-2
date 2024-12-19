<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

trait ReplicaAssertionsTrait
{
    protected function assertSortToReplicaConfigParity(string $primaryIndexName, array $sorting, array $replicas): void
    {
        foreach ($sorting as $sortAttr) {
            $replicaIndexName = $sortAttr['name'];
            $isVirtual = array_key_exists('virtualReplica', $sortAttr) && $sortAttr['virtualReplica'];
            $needle = $isVirtual
                ? "virtual($replicaIndexName)"
                : $replicaIndexName;
            $this->assertContains($needle, $replicas);

            $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName);
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
}
