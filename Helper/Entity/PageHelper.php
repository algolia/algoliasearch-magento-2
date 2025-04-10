<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Page\RecordBuilder as PageRecordBuilder;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class PageHelper extends AbstractEntityHelper
{
    use EntityHelperTrait;
    public const INDEX_NAME_SUFFIX = '_pages';

    public function __construct(
        protected ManagerInterface      $eventManager,
        protected PageCollectionFactory $pageCollectionFactory,
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
        protected IndexNameFetcher      $indexNameFetcher,
        protected PageRecordBuilder     $pageRecordBuilder,
    ) {
        parent::__construct($indexNameFetcher);
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getIndexSettings(?int $storeId = null): array
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(slug)', 'unordered(name)', 'unordered(content)'],
            'attributesToSnippet'  => ['content:7'],
        ];

        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch(
            'algolia_pages_index_before_set_settings',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getPages($storeId, array $pageIds = null)
    {
        $magentoPages = $this->pageCollectionFactory->create()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        if ($pageIds && count($pageIds)) {
            $magentoPages->addFieldToFilter('page_id', ['in' => $pageIds]);
        }

        $excludedPages = $this->getExcludedPageIds($storeId);
        if (count($excludedPages)) {
            $magentoPages->addFieldToFilter('identifier', ['nin' => $excludedPages]);
        }

        $pageIdsToRemove = $pageIds ? array_flip($pageIds) : [];

        $pages = [];

        /** @var Page $page */
        foreach ($magentoPages as $page) {
            $page->setData('store_id', $storeId);

            if (!$page->getId()) {
                continue;
            }

            $pageObject = $this->pageRecordBuilder->buildRecord($page);

            if (isset($pageIdsToRemove[$page->getId()])) {
                unset($pageIdsToRemove[$page->getId()]);
            }
            $pages['toIndex'][] = $pageObject;
        }

        $pages['toRemove'] = array_unique(array_keys($pageIdsToRemove));

        return $pages;
    }

    public function getExcludedPageIds($storeId = null)
    {
        $excludedPages = array_values($this->configHelper->getExcludedPages($storeId));
        foreach ($excludedPages as &$excludedPage) {
            $excludedPage = $excludedPage['attribute'];
        }

        return $excludedPages;
    }

    public function getStores($storeId = null)
    {
        $storeIds = [];

        if ($storeId === null) {
            /** @var \Magento\Store\Model\Store $store */
            foreach ($this->storeManager->getStores() as $store) {
                if ($this->configHelper->isEnabledBackEnd($store->getId()) === false) {
                    continue;
                }

                if ($store->getData('is_active')) {
                    $storeIds[] = $store->getId();
                }
            }
        } else {
            $storeIds = [$storeId];
        }

        return $storeIds;
    }
}
