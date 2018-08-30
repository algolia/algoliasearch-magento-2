<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
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
        $categoryId = $this->request->getParam('entity_id');
        $positions = $this->request->getParam('algolia_merchandising_positions');

        // The merchandising tab was not opened
        if ($positions === null) {
            return;
        }

        $positions = json_decode($positions, true);

        try {
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
        } catch (\AlgoliaSearch\AlgoliaException $e) {
            $message = $e->getMessage();

            if ($message === 'Rules quota exceeded. Please contact us if you need an extended quota.') {
                $message = '
                    The category cannot be merchandised with Algolia 
                    as you hit your <a href="https://www.algolia.com/pricing/" target="_blank">query rules quota</a>. 
                    If you need an extended quota, 
                    please contact us on <a href="mailto:support@algolia.com">support@algolia.com</a>.';
            }

            $phrase = new Phrase($message);
            throw new LocalizedException($phrase);
        }
    }
}
