<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product;

use Magento\Eav\Model\Entity\Attribute as EntityAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeOptionCollection;

class AttributeHelper
{
    /** @var EntityAttribute */
    private $entityAttribute;

    /** @var AttributeOptionCollection */
    private $entityAttributeOptionCollection;

    public function __construct(
        EntityAttribute $entityAttribute,
        AttributeOptionCollection $entityAttributeOptionCollection
    ) {
        $this->entityAttribute = $entityAttribute;
        $this->entityAttributeOptionCollection = $entityAttributeOptionCollection;
    }

    /**
     * Get attribute info by attribute code and entity type
     *
     * @param int|string|Mage\Eav\Model\Entity\Type $entityType
     * @param string $attributeCode
     *
     * @return \Magento\Eav\Model\Entity\Attribute
     */
    public function getAttributeInfo($entityType, $attributeCode)
    {
        return $this->entityAttribute->loadByCode($entityType, $attributeCode);
    }

    /**
     * Get particular option's name and value of the attribute
     *
     * @param int $attributeId
     * @param int $optionId
     *
     * @return \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
     */
    public function getAttributeOptionById($attributeId, $optionId)
    {
        return $this->entityAttributeOptionCollection
            ->setPositionOrder('asc')
            ->setAttributeFilter($attributeId)
            ->setIdFilter($optionId)
            ->setStoreFilter()
            ->load()
            ->getFirstItem();
    }
}
