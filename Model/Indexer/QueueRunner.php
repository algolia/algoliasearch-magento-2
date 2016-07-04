<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Store\Model\StoreManagerInterface;

class QueueRunner implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    private $configHelper;
    private $queue;

    public function __construct(ConfigHelper $configHelper, Queue $queue)
    {
        $this->configHelper = $configHelper;
        $this->queue = $queue;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        if (!$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
//            /** @var Mage_Adminhtml_Model_Session $session */
//            $session = Mage::getSingleton('adminhtml/session');
//            $session->addError('Algolia reindexing failed: You need to configure your Algolia credentials in System > Configuration > Algolia Search.');

            return;
        }

        $this->queue->runCron();

        return $this;
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
