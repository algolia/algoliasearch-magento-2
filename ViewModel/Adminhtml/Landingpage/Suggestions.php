<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage\CollectionFactory as LandingPageCollectionFactory;

class Suggestions
{
    /** @var LandingPageCollectionFactory */
    private $landingPageCollectionFactory;

    /**
     * @param LandingPageCollectionFactory $landingPageCollectionFactory
     */
    public function __construct(LandingPageCollectionFactory $landingPageCollectionFactory)
    {
        $this->landingPageCollectionFactory = $landingPageCollectionFactory;
    }

    public function getNbOfLandingPages()
    {
        $landingPageCollection = $this->landingPageCollectionFactory->create();

        return $landingPageCollection->getSize();
    }
}
