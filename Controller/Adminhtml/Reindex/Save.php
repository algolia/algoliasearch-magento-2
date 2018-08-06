<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Reindex;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use Algolia\AlgoliaSearch\Helper\Data as DataHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Exception\AlgoliaReindexException;
use Algolia\AlgoliaSearch\Exception\AlgoliaProductDisabledException;
use Algolia\AlgoliaSearch\Exception\AlgoliaProductDeletedException;
use Algolia\AlgoliaSearch\Exception\AlgoliaProductNotVisibleException;
use Algolia\AlgoliaSearch\Exception\AlgoliaProductOutOfStockException;

class Save extends \Magento\Backend\App\Action
{

    const MAX_SKUS = 10;

    /** @var ProductFactory */
    private $productFactory;

    /**  @var StoreManagerInterface */
    private $storeManager;

    /** @var DataHelper */
    private $dataHelper;

    /**  @var ProductHelper */
    private $productHelper;

    /**
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
     *
     * @throws AlgoliaReindexException
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');
        $skus = preg_split("/(,|\r\n|\n|\r)/", $this->getRequest()->getParam('skus'));

        $stores = $this->storeManager->getStores();

        foreach ($stores as $storeId => $storeData) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                unset($stores[$storeId]);
            }
        }

        if (empty($skus)) {
            $this->messageManager->addErrorMessage(__('Please, enter at least one SKU'));
        }

        if (count($skus) > self::MAX_SKUS) {
            $this->messageManager->addErrorMessage(
                __(
                    'The maximal number of SKU(s) is %1. Could you please remove some SKU(s) to fit into the limit ?',
                    self::MAX_SKUS
                )
            );
        }

        foreach ($skus as $sku) {
            $sku = trim($sku);
            try {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productFactory->create();
                $product->load($product->getIdBySku($sku));

                if (! $product->getId()) {
                    throw new AlgoliaReindexException(__('Product with SKU <strong>%1</strong> was not found', $sku));
                }

                $this->checkAndReindex($product, $stores);

            } catch (AlgoliaReindexException $e) {
                $this->messageManager->addExceptionMessage($e);

            } catch (AlgoliaProductDisabledException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is disabled in store "%3"',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku(), $stores[$e->getStoreId()]->getName()]
                    )
                );
            } catch (AlgoliaProductDeletedException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is deleted from store "%3"',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku(), $stores[$e->getStoreId()]->getName()]
                    )
                );
            } catch (AlgoliaProductNotVisibleException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is not visible in store "%3"',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku(), $stores[$e->getStoreId()]->getName()]
                    )
                );
            } catch (AlgoliaProductOutOfStockException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is out of stock in store "%3"',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku(), $stores[$e->getStoreId()]->getName()]
                    )
                );
            }
        }

        return $resultRedirect;
    }


    /**
     * Check and reindex one product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array                          $stores
     *
     * @return void
     */
    private function checkAndReindex($product, $stores)
    {
        foreach ($stores as $storeId => $storeData) {
            if (! in_array($storeId, array_values($product->getStoreIds()))) {
                $this->messageManager->addNoticeMessage(
                    __(
                        'The product "%1" (%2) is not associated with store "%3"',
                        [$product->getName(), $product->getSku(), $storeData->getName()]
                    )
                );

                continue;
            }
            $this->dataHelper->canProductBeReindexed($product, $storeId);

            $productIds = [$product->getId()];
            $productIds = array_merge($productIds, $this->productHelper->getParentProductIds($productIds));

            $this->dataHelper->rebuildStoreProductIndex($storeId, $productIds);
            $this->messageManager->addSuccessMessage(
                __(
                    'The Product "%1" (%2) has been reindexed for store "%3"',
                    [$product->getName(), $product->getSku(), $storeData->getName()]
                )
            );
        }
    }
}
