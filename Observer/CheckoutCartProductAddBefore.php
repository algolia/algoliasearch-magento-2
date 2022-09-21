<?php

namespace Algolia\AlgoliaSearch\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;

class CheckoutCartProductAddBefore implements ObserverInterface
{
    /** @var SessionManagerInterface */
    protected $coreSession;

    /**
     * @param SessionManagerInterface $coreSession
     */
    public function __construct(
        SessionManagerInterface $coreSession
    ) {
        $this->coreSession = $coreSession;
    }

    /**
     * @param Observer $observer
     * ['info' => $requestInfo, 'product' => $product]
     */
    public function execute(Observer $observer)
    {
        $requestInfo = $observer->getEvent()->getInfo();

        if (isset($requestInfo['queryID']) && $requestInfo['queryID'] != '') {
            $this->coreSession->setQueryId($requestInfo['queryID']);
        }
    }
}
