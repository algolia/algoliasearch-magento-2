<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use AlgoliaSearch\AlgoliaException;

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

    public function saveQueryRule($storeId, $entityId, $rawPositions, $entityType, $query = null)
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);

        $positions = $this->transformPositions($rawPositions);

        $rule = [
            'objectID' => $this->getQueryRuleId($entityId, $entityType),
            'description' => 'MagentoGeneratedQueryRule',
            'condition' => [
                'pattern' => '',
                'anchoring' => 'is',
                'context' => 'magento-' . $entityType . '-' . $entityId,
            ],
            'consequence' => [
                'promote' => $positions,
            ],
        ];

        if (!is_null($query) && $query != '') {
            $rule['condition']['pattern'] = $query;
        }

        // Not catching AlgoliaSearchException for disabled query rules on purpose
        // It displays correct error message and navigates user to pricing page
        $this->algoliaHelper->saveRule($rule, $productsIndexName);
    }

    public function deleteQueryRule($storeId, $entityId, $entityType)
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
        $ruleId = $this->getQueryRuleId($entityId, $entityType);

        // Not catching AlgoliaSearchException for disabled query rules on purpose
        // It displays correct error message and navigates user to pricing page
        $this->algoliaHelper->deleteRule($productsIndexName, $ruleId);
    }

    private function transformPositions($positions)
    {
        $transformedPositions = [];

        foreach ($positions as $objectID => $position) {
            $transformedPositions[] = [
                'objectID' => (string) $objectID,
                'position' => $position,
            ];
        }

        return $transformedPositions;
    }

    /**
     * @param $storeId
     * @param $entityIdFrom
     * @param $entityIdTo
     * @param $entityType
     *
     * @throws AlgoliaException
     */
    public function copyQueryRules($storeId, $entityIdFrom, $entityIdTo, $entityType)
    {
        $productsIndexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
        $productIndex = $this->algoliaHelper->getIndex($productsIndexName);
        $context = $this->getQueryRuleId($entityIdFrom, $entityType);
        $queryRulesToSet = [];
        try {
            $hitsPerPage = 100;
            $page = 0;
            do {
                $fetchedQueryRules = $productIndex->searchRules([
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
                    $hit['objectID'] = $newContext;
                    if (isset($hit['condition']['context']) && $hit['condition']['context'] == $context) {
                        $hit['condition']['context'] = $newContext;
                    }
                    $queryRulesToSet[] = $hit;
                }

                $page++;
            } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);

            if (!empty($queryRulesToSet)) {
                $productIndex->batchRules($queryRulesToSet, false, false);
            }
        } catch (AlgoliaException $e) {
            // Fail silently if query rules are disabled on the app
            // If QRs are disabled, nothing will happen and the extension will work as expected
            if ($e->getMessage() !== 'Query Rules are not enabled on this application') {
                throw $e;
            }
        }
    }

    private function getQueryRuleId($entityId, $entityType)
    {
        return 'magento-' . $entityType . '-' . $entityId;
    }
}
