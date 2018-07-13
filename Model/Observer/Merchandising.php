<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class Merchandising implements ObserverInterface
{
    private $merchandisingHelper;
    private $storeManager;
    private $request;

    public function __construct(
        StoreManagerInterface $storeManager,
        MerchandisingHelper $merchandisingHelper,
        RequestInterface $request
    ) {
        $this->storeManager = $storeManager;
        $this->merchandisingHelper = $merchandisingHelper;
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        $categoryId = $this->request->getParam('entity_id'); // TODO: Test on EE
        $positions = $this->request->getParam('algolia_merchandising_positions');

        // The merchandising tab was not opened
        if ($positions === null) {
            return;
        }

        $positions = json_decode($positions, true);

        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }

            if (!$positions) {
                $this->merchandisingHelper->deleteQueryRule($store->getId(), $categoryId);
                return;
            }

            $this->merchandisingHelper->saveQueryRule($store->getId(), $categoryId, $positions);
        }
    }
}
