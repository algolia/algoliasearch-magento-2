<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\Data;

class AggregatorHelper {

    /** @var Data */
    private $dataHelper;

    /** @var Product */
    private $productHelper;

    /** @var CategoryHelper */
    private $categoryHelper;

    /** @var PageHelper */
    private $pageHelper;

    /** @var SuggestionHelper */
    private $suggestionHelper;

    /** @var AdditionalSectionHelper */
    private $sectionHelper;

    private $entityIndexes;

    /**
     * EntityHelper constructor.
     * @param Data $dataHelper
     * @param ProductHelper $productHelper
     * @param CategoryHelper $categoryHelper
     * @param PageHelper $pageHelper
     * @param SuggestionHelper $suggestionHelper
     * @param AdditionalSectionHelper $sectionHelper
     */
    public function __construct(
        Data $dataHelper,
        ProductHelper $productHelper,
        CategoryHelper $categoryHelper,
        PageHelper $pageHelper,
        SuggestionHelper $suggestionHelper,
        AdditionalSectionHelper $sectionHelper
    ) {
        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
        $this->categoryHelper = $categoryHelper;
        $this->pageHelper = $pageHelper;
        $this->suggestionHelper = $suggestionHelper;
        $this->sectionHelper = $sectionHelper;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getEntityIndexes($storeId)
    {
        if (!$this->entityIndexes) {
            $this->entityIndexes = [
                'products' => $this->dataHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId),
                'categories' => $this->dataHelper->getIndexName($this->categoryHelper->getIndexNameSuffix(), $storeId),
                'pages' => $this->dataHelper->getIndexName($this->pageHelper->getIndexNameSuffix(), $storeId),
                'suggestions' => $this->dataHelper->getIndexName($this->suggestionHelper->getIndexNameSuffix(),
                    $storeId),
                'sections' => $this->dataHelper->getIndexName($this->sectionHelper->getIndexNameSuffix(),
                    $storeId),
            ];
        }

        return $this->entityIndexes;
    }

    /**
     * @param $entity
     * @param $storeId
     * @return mixed
     */
    public function getIndexNameByEntity($entity, $storeId)
    {
        $indexes = $this->getEntityIndexes($storeId);

        return $indexes[$entity];
    }

}
