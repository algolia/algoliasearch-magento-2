<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cms\Model\Page;
use Magento\Framework\DataObject;
use Magento\Framework\Url;

class PageHelper
{
    private $eventManager;

    private $objectManager;

    private $configHelper;

    private $filterProvider;

    private $storeManager;

    private $storeUrls;

    public function __construct(
        ManagerInterface $eventManager,
        ObjectManagerInterface $objectManager,
        ConfigHelper $configHelper,
        FilterProvider $filterProvider,
        StoreManagerInterface $storeManager
    ) {
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;
        $this->configHelper = $configHelper;
        $this->filterProvider = $filterProvider;
        $this->storeManager = $storeManager;
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

    public function getPages($storeId)
    {
        /** @var \Magento\Cms\Model\Page $pageModel */
        $pageModel = $this->objectManager->create('\Magento\Cms\Model\Page');

        /** @var \Magento\Cms\Model\ResourceModel\Page\Collection $magentoPages */
        $magentoPages = $pageModel->getCollection();
        $magentoPages = $magentoPages
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        $excludedPages = array_values($this->configHelper->getExcludedPages());

        foreach ($excludedPages as &$excludedPage) {
            $excludedPage = $excludedPage['attribute'];
        }

        $pages = [];

        /** @var Page $page */
        foreach ($magentoPages as $page) {
            if (in_array($page->getIdentifier(), $excludedPages)) {
                continue;
            }

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
            $pageObject['url'] = $this->getStoreUrl($storeId)
                                      ->getUrl(
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

            $pages[] = $pageObject;
        }

        return $pages;
    }

    private function getStoreUrl($storeId)
    {
        if ($this->storeUrls == null) {
            $this->storeUrls = [];
            $storeIds = $this->getStores(null);

            foreach ($storeIds as $storeId) {
                // ObjectManager used instead of UrlFactory because UrlFactory will return UrlInterface which
                // may cause a backend Url object to be returned

                /** @var Url $url */
                $url = $this->objectManager->create('Magento\Framework\Url');
                $url->setData('store', $storeId);
                $this->storeUrls[$storeId] = $url;
            }
        }

        if (array_key_exists($storeId, $this->storeUrls)) {
            return $this->storeUrls[$storeId];
        }

        return null;
    }

    private function getStores($storeId)
    {
        $storeIds = [];

        if ($storeId == null) {
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
        if (!empty($completeRemoveTags) && $s) {
            $dom = new \DOMDocument();
            if (@$dom->loadHTML(mb_convert_encoding($s, 'HTML-ENTITIES', 'UTF-8'))) {
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
