<?php

namespace Algolia\AlgoliaSearch\Service\AdditionalSection;

use Algolia\AlgoliaSearch\Api\Builder\QueueBuilderInterface;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder as AdditionalSectionIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;

class QueueBuilder implements QueueBuilderInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ){}

    public function buildQueue(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        /** @uses AdditionalSectionIndexBuilder::buildIndexFull() */
        $this->queue->addToQueue(
            AdditionalSectionIndexBuilder::class,
            'buildIndexFull',
            ['storeId' => $storeId],
            1
        );
    }
}
