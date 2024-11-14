<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;

class QueueRunner implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public const INDEXER_ID = 'algolia_queue_runner';

    public function __construct(
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return;
        }

        $this->queue->runCron();
    }

    public function executeList(array $ids)
    {
        return $this;
    }

    public function executeRow($id)
    {
        return $this;
    }
}
