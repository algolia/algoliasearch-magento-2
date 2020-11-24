<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;

class PageHelper
{
    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var PageCollectionFactory
     */
    private $pageCollectionFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlFactory
     */
    private $frontendUrlFactory;

    /**
     * PageHelper constructor.
     *
     * @param ManagerInterface      $eventManager
     * @param PageCollectionFactory $pageCollectionFactory
     * @param ConfigHelper          $configHelper
     * @param FilterProvider        $filterProvider
     * @param StoreManagerInterface $storeManager
     * @param UrlFactory          $frontendUrlFactory
     */
    public function __construct(
        ManagerInterface $eventManager,
        PageCollectionFactory $pageCollectionFactory,
        ConfigHelper $configHelper,
        FilterProvider $filterProvider,
        StoreManagerInterface $storeManager,
        UrlFactory $frontendUrlFactory
    ) {
        $this->eventManager = $eventManager;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->configHelper = $configHelper;
        $this->filterProvider = $filterProvider;
        $this->storeManager = $storeManager;
        $this->frontendUrlFactory = $frontendUrlFactory;
    }

    public function getIndexNameSuffix()
    {
        return '_pages';
    }

    public function getIndexSettings($storeId)
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
        /** @var \Magento\Cms\Model\ResourceModel\Page\Collection $magentoPages */
        $magentoPages = $this->pageCollectionFactory->create()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        if ($pageIds && count($pageIds)) {
            $magentoPages->addFieldToFilter('page_id', ['in' => $pageIds]);
        }

        $excludedPages = $this->getExcludedPageIds();
        if (count($excludedPages)) {
            $magentoPages->addFieldToFilter('identifier', ['nin' => $excludedPages]);
        }

        $pageIdsToRemove = $pageIds ? array_flip($pageIds) : [];

        $pages = [];

        $frontendUrlBuilder = $this->frontendUrlFactory->create()->setScope($storeId);

        /** @var Page $page */
        foreach ($magentoPages as $page) {
            $pageObject = [];

            $pageObject['slug'] = $page->getIdentifier();
            $pageObject['name'] = $page->getTitle();

            $page->setData('store_id', $storeId);

            if (!$page->getId()) {
                continue;
            }

            $content = $page->getContent();
            if ($this->configHelper->getRenderTemplateDirectives()) {
                $content = $this->filterProvider->getPageFilter()->filter($content);
            }

            $pageObject['objectID'] = $page->getId();
            $pageObject['url'] = $frontendUrlBuilder->getUrl(
                null,
                [
                    '_direct' => $page->getIdentifier(),
                    '_secure' => $this->configHelper->useSecureUrlsInFrontend($storeId),
                ]
            );
            $pageObject['content'] = $this->strip($content, ['script', 'style']);

            $transport = new DataObject($pageObject);
            $this->eventManager->dispatch(
                'algolia_after_create_page_object',
                ['page' => $transport, 'pageObject' => $page]
            );
            $pageObject = $transport->getData();

            if (isset($pageIdsToRemove[$page->getId()])) {
                unset($pageIdsToRemove[$page->getId()]);
            }
            $pages['toIndex'][] = $pageObject;
        }

        $pages['toRemove'] = array_unique(array_keys($pageIdsToRemove));

        return $pages;
    }

    public function getExcludedPageIds()
    {
        $excludedPages = array_values($this->configHelper->getExcludedPages());
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

    private function strip($s, $completeRemoveTags = [])
    {
        if ($completeRemoveTags && $completeRemoveTags !== [] && $s) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($s, 'HTML-ENTITIES', 'UTF-8'));
            libxml_use_internal_errors(false);

            $toRemove = [];
            foreach ($completeRemoveTags as $tag) {
                $removeTags = $dom->getElementsByTagName($tag);

                foreach ($removeTags as $item) {
                    $toRemove[] = $item;
                }
            }

            foreach ($toRemove as $item) {
                $item->parentNode->removeChild($item);
            }

            $s = $dom->saveHTML();
        }

        $s = html_entity_decode($s, null, 'UTF-8');

        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = preg_replace('/&nbsp;/', ' ', $s);
        $s = preg_replace('!\s+!', ' ', $s);
        $s = preg_replace('/\{\{[^}]+\}\}/', ' ', $s);
        $s = strip_tags($s);
        $s = trim($s);

        return $s;
    }
}
