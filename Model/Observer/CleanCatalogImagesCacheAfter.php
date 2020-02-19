<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class CleanCatalogImagesCacheAfter implements ObserverInterface
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var ManagerInterface */
    private $messageManager;

    /**
     * @param ConfigHelper $configHelper
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ConfigHelper $configHelper,
        ManagerInterface $messageManager
    ) {
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
    }

    /**
     * Add Notice for Product Reindexing after image flush
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->messageManager->addWarningMessage(__('
            Algolia Warning: The image cache has been cleared. 
            All indexed image links will become invalid because the file will be nonexistent. 
            Please run a full reindex of your catalog data to resolve broken images in your Algolia Search.
        '));
    }
}
