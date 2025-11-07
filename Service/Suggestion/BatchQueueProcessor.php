<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Source\Suggestions;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder as SuggestionIndexBuilder;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected AutocompleteHelper $autocompleteHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ){}

    public function processBatch(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false
            || $this->autocompleteHelper->getSuggestionsMode($storeId) !== Suggestions::SUGGESTIONS_MAGENTO) {
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
