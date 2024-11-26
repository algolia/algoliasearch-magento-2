<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Reindex;

use Algolia\AlgoliaSearch\Exception\ProductDeletedException;
use Algolia\AlgoliaSearch\Exception\ProductDisabledException;
use Algolia\AlgoliaSearch\Exception\ProductNotVisibleException;
use Algolia\AlgoliaSearch\Exception\ProductOutOfStockException;
use Algolia\AlgoliaSearch\Exception\UnknownSkuException;
use Algolia\AlgoliaSearch\Helper\Data as DataHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Save extends \Magento\Backend\App\Action
{
    public const MAX_SKUS = 10;

    public function __construct(
        protected Context $context,
        protected ProductRepositoryInterface $productRepository,
        protected StoreManagerInterface $storeManager,
        protected DataHelper $dataHelper,
        protected ProductHelper $productHelper,
        protected ProductIndexBuilder $productIndexBuilder
    ) {
        parent::__construct($context);
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
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
            $this->messageManager->addErrorMessage(__('Please, enter at least one SKU.'));
            return $resultRedirect;
        }

        if (count($skus) > self::MAX_SKUS) {
            $this->messageManager->addErrorMessage(
                __(
                    'The maximum number of SKU(s) is %1. Please remove some SKU(s) and try again',
                    self::MAX_SKUS
                )
            );
            return $resultRedirect;
        }

        foreach ($skus as $sku) {
            $sku = trim($sku);

            try {

                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productRepository->get($sku);
                if (! $product->getId()) {
                    throw new UnknownSkuException(__('Product with SKU "%1" was not found.', $sku));
                }

                $this->checkAndReindex($product, $stores);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addExceptionMessage($e, __('Product with SKU "%1" was not found.', $sku));
            } catch (UnknownSkuException $e) {
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            } catch (ProductDeletedException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is deleted.',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku()]
                    )
                );
            } catch (ProductOutOfStockException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __(
                        'The product "%1" (%2) is out of stock.',
                        [$e->getProduct()->getName(), $e->getProduct()->getSku()]
                    )
                );
            }
        }

        return $resultRedirect;
    }

    /**
     * @param $product
     * @param $stores
     * @return void
     * @throws NoSuchEntityException
     */
    protected function checkAndReindex($product, $stores)
    {
        $websites = $this->storeManager->getWebsites();
        $storeGroup = $this->storeManager->getGroups();

        foreach ($stores as $storeId => $storeData) {
            if (! in_array($storeId, array_values($product->getStoreIds()))) {
                $this->messageManager->addNoticeMessage(
                    __(
                        'The product "%1" (%2) is not associated with store "%3 / %4 / %5".',
                        [
                            $product->getName(),
                            $product->getSku(),
                            $websites[$storeData->getWebsiteId()]->getName(),
                            $storeGroup[$storeData->getStoreGroupId()]->getName(),
                            $storeData->getName(),
                        ]
                    )
                );

                continue;
            }

            try {
                $product = $this->productRepository->get($product->getSku(), false, $storeId);
                $this->productHelper->canProductBeReindexed($product, $storeId);
            } catch (ProductDisabledException $e) {
                // Product status is a Website specific attribute
                $this->messageManager->addErrorMessage(
                    __(
                        'The product "%1" (%2) is disabled in store "%3 / %4 / %5".',
                        [
                            $e->getProduct()->getName(),
                            $e->getProduct()->getSku(),
                            $websites[$stores[$e->getStoreId()]->getWebsiteId()]->getName(),
                            $storeGroup[$stores[$e->getStoreId()]->getStoreGroupId()]->getName(),
                            $stores[$e->getStoreId()]->getName(),
                        ]
                    )
                );

                continue;
            } catch (ProductNotVisibleException $e) {
                // Product visibility is a Store specific attribute
                // If it's a simple product that is not visible, try to index its parent if it exists
                if ($e->getProduct()->getTypeId() == 'simple') {
                    $parentId = $this->productHelper->getParentProductIds([$e->getProduct()->getId()]);
                    if (isset($parentId[0])) {
                        $parentId = $parentId[0];
                        /** @var \Magento\Catalog\Model\Product $parentProduct */
                        $parentProduct = $this->productRepository->getById($parentId, false, $e->getStoreId());
                        $this->messageManager->addNoticeMessage(
                            __(
                                'The product "%1" (%2) is not visible but it has a parent product "%3" (%4).',
                                [
                                    $e->getProduct()->getName(),
                                    $e->getProduct()->getSku(),
                                    $parentProduct->getName(),
                                    $parentProduct->getSku(),
                                ]
                            )
                        );

                        $this->checkAndReindex($parentProduct, $stores);

                        continue;
                    }
                } else {
                    $this->messageManager->addErrorMessage(
                        __(
                            'The product "%1" (%2) is not visible in store "%3 / %4 / %5".',
                            [
                                $e->getProduct()->getName(),
                                $e->getProduct()->getSku(),
                                $websites[$stores[$e->getStoreId()]->getWebsiteId()]->getName(),
                                $storeGroup[$stores[$e->getStoreId()]->getStoreGroupId()]->getName(),
                                $stores[$e->getStoreId()]->getName(),
                            ]
                        )
                    );

                    continue;
                }
            }

            $productIds = [$product->getId()];
            $productIds = array_merge($productIds, $this->productHelper->getParentProductIds($productIds));

            $this->productIndexBuilder->rebuildIndexIds($storeId, $productIds);
            $this->messageManager->addSuccessMessage(
                __(
                    'The Product "%1" (%2) has been reindexed for store "%3 / %4 / %5".',
                    [
                        $product->getName(),
                        $product->getSku(),
                        $websites[$storeData->getWebsiteId()]->getName(),
                        $storeGroup[$storeData->getStoreGroupId()]->getName(),
                        $storeData->getName(),
                    ]
                )
            );
        }
    }
}
