<?php

namespace Algolia\AlgoliaSearch\Model\Sitemap\ItemProvider;

use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage\CollectionFactory;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;

/**
 * The purpose of this class is to add the created landing pages to the sitemaps
 */
class LandingPage
{
    /** @var CollectionFactory */
    private $landingPageCollectionFactory;

    /** @var SitemapItemInterfaceFactory */
    private $itemFactory;

    public function __construct(
        CollectionFactory $landingPageCollectionFactory,
        SitemapItemInterfaceFactory $itemFactory
    ) {
        $this->landingPageCollectionFactory = $landingPageCollectionFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $collection = $this->landingPageCollectionFactory->create();
        $collection->addFieldToFilter('store_id', [0, $storeId]);
        $items = [];

        foreach ($collection as $landingPage) {
            $items[] = $this->itemFactory->create([
                'url' => $landingPage->getUrlKey(),
                'priority' => 1.0,
                'changeFrequency' => 'daily',
            ]);
        }

        return $items;
    }
}
