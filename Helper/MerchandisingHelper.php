<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;

class MerchandisingHelper
{
    /** @var Data */
    private $coreHelper;

    /** @var ProductHelper */
    private $productHelper;

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    public function __construct(
        Data $coreHelper,
        ProductHelper $productHelper,
        AlgoliaHelper $algoliaHelper
    ) {
        $this->coreHelper = $coreHelper;
        $this->productHelper = $productHelper;
        $this->algoliaHelper = $algoliaHelper;
    }

    /**
     * @param int $storeId
     * @param int $entityId
     * @param array $rawPositions
     * @param string $entityType
     * @param string|null $query
     * @param string|null $banner
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveQueryRule(int    $storeId,
                                  int    $entityId,
                                  array  $rawPositions,
                                  string $entityType,
                                  string $query = null,
                                  string $banner = null): void
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->productHelper->getIndexName($storeId);

        $positions = $this->transformPositions($rawPositions);
        $condition = [
            'pattern' => '',
            'anchoring' => 'is',
            'context' => 'magento-' . $entityType . '-' . $entityId,
        ];

        $rule = [
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID => $this->getQueryRuleId($entityId, $entityType),
            'description' => 'MagentoGeneratedQueryRule',
            'consequence' => [
                'filterPromotes' => true,
                'promote' => $positions,
            ],
        ];

        if (!is_null($query) && $query != '') {
            $condition['pattern'] = $query;
        }

        if (!is_null($banner)) {
            $rule['consequence']['userData']['banner'] = $banner;
        }

        if ($entityType == 'query') {
            unset($condition['context']);
        }

        if (in_array($entityType, ['query', 'landingpage'])) {
            $rule['tags'] = ['visual-editor'];
        }

        $rule['conditions'] = [$condition];

        // Not catching AlgoliaSearchException for disabled query rules on purpose
        // It displays correct error message and navigates user to pricing page
        $this->algoliaHelper->saveRule($rule, $productsIndexName, false, $storeId);
    }

    /**
     * @param int $storeId
     * @param int $entityId
     * @param string $entityType
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteQueryRule(int $storeId, int $entityId, string $entityType): void
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->productHelper->getIndexName($storeId);
        $ruleId = $this->getQueryRuleId($entityId, $entityType);

        // Not catching AlgoliaSearchException for disabled query rules on purpose
        // It displays correct error message and navigates user to pricing page
        $this->algoliaHelper->deleteRule($productsIndexName, $ruleId, false, $storeId);
    }

    /**
     * @param array $positions
     * @return array
     */
    private function transformPositions(array $positions): array
    {
        $transformedPositions = [];

        foreach ($positions as $objectID => $position) {
            $transformedPositions[] = [
                AlgoliaConnector::ALGOLIA_API_OBJECT_ID => (string) $objectID,
                'position' => $position,
            ];
        }

        return $transformedPositions;
    }

    /**
     * @param int $storeId
     * @param int $entityIdFrom
     * @param int $entityIdTo
     * @param string $entityType
     * @return void
     * @throws AlgoliaException|\Magento\Framework\Exception\NoSuchEntityException
     */
    public function copyQueryRules(int $storeId, int $entityIdFrom, int $entityIdTo, string $entityType): void
    {
        $productsIndexName = $this->productHelper->getIndexName($storeId);
        $client = $this->algoliaHelper->getClient($storeId);
        $context = $this->getQueryRuleId($entityIdFrom, $entityType);
        $queryRulesToSet = [];

        try {
            $hitsPerPage = 100;
            $page = 0;
            do {
                $fetchedQueryRules = $client->searchRules($productsIndexName, [
                    'context' => $context,
                    'page' => $page,
                    'hitsPerPage' => $hitsPerPage,
                ]);

                if (!$fetchedQueryRules || !array_key_exists('hits', $fetchedQueryRules)) {
                    break;
                }

                foreach ($fetchedQueryRules['hits'] as $hit) {
                    unset($hit['_highlightResult']);

                    $newContext = $this->getQueryRuleId($entityIdTo, $entityType);
                    $hit[AlgoliaConnector::ALGOLIA_API_OBJECT_ID] = $newContext;
                    if (isset($hit['condition']['context']) && $hit['condition']['context'] == $context) {
                        $hit['condition']['context'] = $newContext;
                    }

                    if (isset($hit['conditions']) && is_array($hit['conditions'])) {
                        foreach ($hit['conditions'] as &$condition) {
                            if (isset($condition['context']) && $condition['context'] == $context) {
                                $condition['context'] = $newContext;
                            }
                        }
                    }

                    $queryRulesToSet[] = $hit;
                }

                $page++;
            } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);

            if (!empty($queryRulesToSet)) {
                $client->saveRules($productsIndexName, $queryRulesToSet, false, false);
            }
        } catch (AlgoliaException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @param int $entityId
     * @param string $entityType
     * @return string
     */
    private function getQueryRuleId(int $entityId, string $entityType): string
    {
        return 'magento-' . $entityType . '-' . $entityId;
    }
}
