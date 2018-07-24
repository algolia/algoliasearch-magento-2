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
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;

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
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     *
     * @param Context               $context
     * @param ProductFactory        $productFactory
     * @param StoreManagerInterface $storeManager
     * @param DataHelper            $dataHelper
     * @param ProductHelper         $productHelper
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        StoreManagerInterface $storeManager,
        DataHelper $dataHelper,
        ProductHelper $productHelper
    ) {
        parent::__construct($context);
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
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

        foreach ($skus as $sku) {
            $sku = trim($sku);
            try {
                $product = $this->productFactory->create();
                $product->load($product->getIdBySku($sku));

                if (! $product->getId()) {
                    throw new \Exception(__('Unknown product with sku "%1"', $sku));
                }

                $this->checkAndReindex($product, $storeIds);

            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e);
            }
        }

        return $resultRedirect;
    }


    protected function checkAndReindex($product, $storeIds)
    {
        foreach ($storeIds as $storeId) {
            if (! in_array($storeId, array_values($product->getStoreIds()))) {
                $this->messageManager->addNoticeMessage(
                    __(
                        'Product "%1" (%2) is not associated to store %3',
                        [$product->getName(), $product->getSku(), $storeId]
                    )
                );

                continue;
            }
            $this->dataHelper->productCanBeReindexed($product, $storeId, true);

            $productIds = [$product->getId()];
            $productIds = array_merge($productIds, $this->productHelper->getParentProductIds($productIds));

            $this->dataHelper->rebuildStoreProductIndex($storeId, $productIds);
            $this->messageManager->addSuccessMessage(
                __(
                    'Product "%1" (%2) has been reindexed (store %3)',
                    [$product->getName(), $product->getSku(), $storeId]
                )
            );
        }
    }
}
