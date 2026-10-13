<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Magento\CatalogRule\Model\ResourceModel\Rule as CoreRule;

class Rule extends CoreRule
{
    /**
     * Fetch Rules for every customer groups at once
     */
    public function getAllRulePricesFromProduct(\DateTimeInterface $date, $websiteId, $productIds)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('catalogrule_product_price'))
            ->where('rule_date = ?', $date->format('Y-m-d'))
            ->where('website_id = ?', $websiteId)
            ->where('product_id IN(?)', $productIds, \Zend_Db::INT_TYPE);

        return $connection->fetchAll($select);
    }
}
