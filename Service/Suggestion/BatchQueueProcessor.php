<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinitionFactory;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder as SuggestionIndexBuilder;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected Queue $queue,
        protected SuggestionHelper $suggestionHelper,
        protected IndexSettingsComparator $indexSettingsComparator,
        protected IndexOptionsBuilder $indexOptionsBuilder,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected JobDefinitionFactory $jobDefinitionFactory,
        protected JobContextFactory $jobContextFactory
    ){}

    public function processBatch(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
        $suggestionSettings = $this->suggestionHelper->getIndexSettings($storeId);

        if (!$this->indexSettingsComparator->matches($indexOptions, $suggestionSettings)) {
            /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
            $this->queue->addJob(
                $this->jobDefinitionFactory->create([
                    'className' => IndicesConfigurator::class,
                    'method'    => 'saveConfigurationToAlgolia',
                    'storeId'   => $storeId,
                    'data'      => [
                        'useTmpIndex' => true,
                        'filteredEntities' => ['suggestions'],
                    ],
                ])
            );
        }

        /** @uses SuggestionIndexBuilder::buildIndexFull() */
        $this->queue->addJob(
            $this->jobDefinitionFactory->create([
                'className' => SuggestionIndexBuilder::class,
                'method'    => 'buildIndexFull',
                'storeId'   => $storeId,
            ])
        );
    }
}
