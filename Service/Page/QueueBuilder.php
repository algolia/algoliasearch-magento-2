<?php

namespace Algolia\AlgoliaSearch\Service\Page;

use Algolia\AlgoliaSearch\Api\Builder\QueueBuilderInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Page\IndexBuilder as PageIndexBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class QueueBuilder implements QueueBuilderInterface
{
    public function __construct(
        protected Data $dataHelper,
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ){}

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @return void
     * @throws NoSuchEntityException
     */
    public function buildQueue(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        if ($this->isPagesInAdditionalSections($storeId)) {
            $data = ['storeId' => $storeId];
            if (is_array($entityIds) && count($entityIds) > 0) {
                $data['options'] = ['entityIds' => $entityIds];
            }

            /** @uses PageIndexBuilder::buildIndexFull() */
            $this->queue->addToQueue(
                PageIndexBuilder::class,
                'buildIndexFull',
                $data,
                is_array($entityIds) ? count($entityIds) : 1
            );
        }
    }

    /**
     * @param $storeId
     * @return bool
     */
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
