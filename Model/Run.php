<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\RunInterface;
use Magento\Framework\DataObject\IdentityInterface;

class Run extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, RunInterface
{
    const CACHE_TAG = 'algoliasearch_queue_run';

    protected $_cacheTag = 'algoliasearch_queue_run';

    protected $_eventPrefix = 'algoliasearch_queue_run';

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Algolia\AlgoliaSearch\Model\ResourceModel\Run');
    }

    /**
     * @return array|string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
