<?php

namespace Algolia\AlgoliaSearch\Service\AdditionalSection;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinitionFactory;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder as AdditionalSectionIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected Queue $queue,
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

        /** @uses AdditionalSectionIndexBuilder::buildIndexFull() */
        $this->queue->addJob(
            $this->jobDefinitionFactory->create([
                'className' => AdditionalSectionIndexBuilder::class,
                'method'    => 'buildIndexFull',
                'storeId'   => $storeId,
            ])
        );
    }
}
