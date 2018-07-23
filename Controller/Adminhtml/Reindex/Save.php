<?php
/**
 * Module Algolia Algoliasearch
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Reindex;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use Algolia\AlgoliaSearch\Helper\Data as DataHelper;

class Save extends \Magento\Backend\App\Action
{

    const MAX_SKUS = 10;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DataHelper
     */
    protected $dataHelper;

    /**
     *
     * @param Context               $context
     * @param ProductFactory        $productFactory
     * @param StoreManagerInterface $storeManager
     * @param DataHelper            $dataHelper
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        StoreManagerInterface $storeManager,
        DataHelper $dataHelper
    ) {
        parent::__construct($context);
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Execute the action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');

        $skus = explode(",", $this->getRequest()->getParam('skus'));
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $key => $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                unset($storeIds[$key]);
            }
        }

        if (empty($skus)) {
            $this->messageManager->addErrorMessage(__('Please enter one or more sku(s)'));
        }

        if (count($skus) > self::MAX_SKUS) {
            $this->messageManager->addErrorMessage(__('Please enter less than %1 sku(s)', self::MAX_SKUS));
        }

        try {
            foreach ($skus as $sku) {
                $this->checkReindex($sku, $storeIds);
                $this->messageManager->addSuccessMessage(__('Product with SKU %1 is OK', $sku));
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        return $resultRedirect;
    }


    protected function checkReindex($sku, $storeIds)
    {
        $product = $this->productFactory->create();
        $product->load($product->getIdBySku($sku));

        if (! $product->getId()) {
            throw new \Exception(__('Unknown product with sku "%1"', $sku));
        }

        foreach ($storeIds as $storeId) {
            $this->dataHelper->productCanBeReindexed($product, $storeId, true);
        }
    }

}
