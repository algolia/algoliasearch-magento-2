<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Service\AdditionalSection\RecordBuilder as AdditionalSectionRecordBuilder;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

class AdditionalSectionHelper extends AbstractEntityHelper
{
    use EntityHelperTrait;
    public const INDEX_NAME_SUFFIX = '_section';

    public function __construct(
        protected ManagerInterface               $eventManager,
        protected CollectionFactory              $collectionFactory,
        protected Config                         $eavConfig,
        protected AdditionalSectionRecordBuilder $additionalSectionRecordBuilder,
        protected IndexNameFetcher               $indexNameFetcher,
    ) {
        parent::__construct($indexNameFetcher);
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getIndexSettings(?int $storeId = null): array
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(value)'],
        ];

        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch(
            'algolia_additional_sections_index_before_set_settings',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getAttributeValues($storeId, $section): array
    {
        $attributeCode = $section['name'];

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products = $this->collectionFactory->create()
            ->addStoreFilter($storeId)
            ->addAttributeToFilter($attributeCode, ['notnull' => true])
            ->addAttributeToFilter($attributeCode, ['neq' => ''])
            ->addAttributeToSelect($attributeCode);

        $usedAttributeValues = array_unique($products->getColumnValues($attributeCode));

        $attributeModel = $this->eavConfig->getAttribute('catalog_product', $attributeCode)->setStoreId($storeId);

        $values = $attributeModel->getSource()->getOptionText(
            implode(',', $usedAttributeValues)
        );

        if ($values && is_array($values) === false) {
            $values = [$values];
        }

        if (!$values || count($values) === 0) {
            $values = array_unique($products->getColumnValues($attributeCode));
        }

        $values = array_map(function ($value) use ($section, $storeId) {
            $dataObject = new DataObject([
                'value' => $value,
                'section' => $section,
                'store_id' => $storeId
            ]);

            return $this->additionalSectionRecordBuilder->buildRecord($dataObject);
        }, $values);

        return $values;
    }
}
