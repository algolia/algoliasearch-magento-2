<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Page\IndexBuilder as PageIndexBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Page implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected PageHelper $pageHelper,
        protected Data $dataHelper,
        protected AlgoliaHelper $algoliaHelper,
        protected Queue $queue,
        protected ConfigHelper $configHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    /**
     * @param $ids
     * @return void
     */
    public function execute($ids)
    {
        $storeIds = $this->pageHelper->getStores();

        foreach ($storeIds as $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

                return;
            }

            if ($this->isPagesInAdditionalSections($storeId)) {
                $data = ['storeId' => $storeId];
                if (is_array($ids) && count($ids) > 0) {
                    $data['pageIds'] = $ids;
                }

                /** @uses PageIndexBuilder::buildIndex() */
                $this->queue->addToQueue(
                    PageIndexBuilder::class,
                    'buildIndex',
                    $data,
                    is_array($ids) ? count($ids) : 1
                );
            }
        }
    }

    /**
     * @return void
     */
    public function executeFull()
    {
        $this->execute(null);
    }

    /**
     * @param array $ids
     * @return void
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @param $id
     * @return void
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param $storeId
     * @return bool
     */
    protected function isPagesInAdditionalSections($storeId)
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
