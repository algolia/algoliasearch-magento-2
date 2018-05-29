<?php
namespace Algolia\AlgoliaSearch\Model;


class Job extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'algoliasearch_queue_job';

    protected $_cacheTag = 'algoliasearch_queue_job';

    protected $_eventPrefix = 'algoliasearch_queue_job';

    protected function _construct()
    {
        $this->_init('Algolia\AlgoliaSearch\Model\ResourceModel\Job');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}
