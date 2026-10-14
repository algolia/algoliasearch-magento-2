<?php

namespace Algolia\AlgoliaSearch\Service\Page;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinitionFactory;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Page\IndexBuilder as PageIndexBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected PageHelper $pageHelper,
        protected IndexSettingsComparator $indexSettingsComparator,
        protected IndexOptionsBuilder $indexOptionsBuilder,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected JobDefinitionFactory $jobDefinitionFactory,
        protected JobContextFactory $jobContextFactory
    ){}

    /**
     * @throws NoSuchEntityException
     */
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
        $pageSettings = $this->pageHelper->getIndexSettings($storeId);

        if (!$this->indexSettingsComparator->matches($indexOptions, $pageSettings)) {
            /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
            $this->queue->addJob(
                $this->jobDefinitionFactory->create([
                    'className' => IndicesConfigurator::class,
                    'method'    => 'saveConfigurationToAlgolia',
                    'storeId'   => $storeId,
                    'data'      => [
                        'useTmpIndex' => (!$entityIds),
                        'filteredEntities' => ['pages'],
                    ],
                ])
            );
        }


        if ($this->isPagesInAdditionalSections($storeId)) {
            $data = [];
            if (is_array($entityIds) && count($entityIds) > 0) {
                $data['options'] = ['entityIds' => $entityIds];
            }

            /** @uses PageIndexBuilder::buildIndexFull() */
            $this->queue->addJob(
                $this->jobDefinitionFactory->create([
                    'className' => PageIndexBuilder::class,
                    'method'    => 'buildIndexFull',
                    'storeId'   => $storeId,
                    'data'      => $data,
                ]),
                $this->jobContextFactory->create(['dataSize' => is_array($entityIds) ? count($entityIds) : 1])
            );
        }
    }

    protected function isPagesInAdditionalSections($storeId): bool
    {
        $sections = $this->configHelper->getAutocompleteSections($storeId);
        foreach ($sections as $section) {
            if ($section['name'] === 'pages') {
                return true;
            }
        }

        return false;
    }
}
