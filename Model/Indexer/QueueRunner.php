<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;

class QueueRunner implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $configHelper;
    private $queue;
    private $messageManager;

    public function __construct(ConfigHelper $configHelper, Queue $queue, ManagerInterface $messageManager)
    {
        $this->configHelper = $configHelper;
        $this->queue = $queue;
        $this->messageManager = $messageManager;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        if (!$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                throw new \Exception($errorMessage);
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $this->queue->runCron();

        return;
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
