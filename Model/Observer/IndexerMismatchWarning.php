<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceProcessor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Mview\View\StateInterface;

class IndexerMismatchWarning implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var StateInterface
     */
    protected $mviewState;

    /**
     * IndexerWarning constructor.
     *
     * @param StateInterface $mviewState
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        StateInterface $mviewState,
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
        $this->mviewState = $mviewState;
    }

    public function execute(Observer $observer)
    {
        if (
            $this->mviewState->loadByView('algolia_products')->getMode() == StateInterface::MODE_DISABLED &&
            $this->mviewState->loadByView(PriceProcessor::INDEXER_ID)->getMode() == StateInterface::MODE_ENABLED
        ) {
            $this->messageManager->addWarningMessage(__('Warning; Algolia Product indexer is set to "Update on Save", but Catalog Product Price indexer is set to "Update on Schedule". This might cause problems with the pricing being synced to Algolia. Please set both to the same setting.'));
        }
    }
}
