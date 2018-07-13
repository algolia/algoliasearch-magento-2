<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;

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

    public function saveQueryRule($storeId, $categoryId, $rawPositions)
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);

        $positions = $this->transformPositions($rawPositions);

        $rule = [
            'objectID' => $this->getQueryRuleId($categoryId),
            'description' => 'MagentoGeneratedQueryRule',
            'condition' => [
                'pattern' => '',
                'anchoring' => 'is',
                'context' => 'magento-category-'.$categoryId,
            ],
            'consequence' => [
                'promote' => $positions,
            ]
        ];

        $this->algoliaHelper->saveRule($rule, $productsIndexName);
    }

    public function deleteQueryRule($storeId, $categoryId)
    {
        if ($this->coreHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $productsIndexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
        $ruleId = $this->getQueryRuleId($categoryId);

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

    private function getQueryRuleId($categoryId)
    {
        return 'magento-category-'.$categoryId;
    }
}
