<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Builder\QueueBuilderInterface;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder as SuggestionIndexBuilder;

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

        /** @uses SuggestionIndexBuilder::buildIndexFull() */
        $this->queue->addToQueue(
            SuggestionIndexBuilder::class,
            'buildIndexFull',
            ['storeId' => $storeId],
            1
        );
    }
}
