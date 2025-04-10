<?php

namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;

class ProcessQueue
{
    public function __construct(
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {}

    public function execute()
    {
        if (!$this->configHelper->isQueueIndexerEnabled()) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return;
        }

        $this->queue->runCron();
    }
}
