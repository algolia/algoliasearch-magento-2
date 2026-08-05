<?php

namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;

class ProcessQueue
{
    public function __construct(
        protected QueueHelper $queueHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {}

    public function execute()
    {
        if (!$this->queueHelper->isQueueActive() || !$this->queueHelper->useBuiltInCron()) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return;
        }

        $this->queue->runCron();
    }
}
