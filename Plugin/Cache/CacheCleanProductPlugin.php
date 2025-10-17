<?php

namespace Algolia\AlgoliaSearch\Plugin\Cache;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\CacheHelper;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize as Cache;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;

class CacheCleanProductPlugin
{
    protected array $originalData = [];

    public function __construct(
        protected Cache $cache,
        protected ConfigHelper $configHelper,
        protected CacheHelper $cacheHelper
    ) { }

    public function beforeSave(ProductResource $subject, Product $product): void
    {
        $this->originalData[$product->getSku()] = $product->getOrigData();
    }

    public function afterSave(ProductResource $subject, ProductResource $result, Product $product): ProductResource
    {
        $original = $this->originalData[$product->getSku()] ?? [];

        // In case of a product duplication
        if (empty($original)) {
            return $result;
        }

        $storeId = $product->getStoreId();

        $shouldClearCache =
            $this->isEligibleNewProduct($product)
            || $this->hasEnablementChanged($original, $product->getData())
            || $this->hasVisibilityChanged($original, $product->getData(), $storeId)
            || $this->hasStockChanged($original, $product->getData(), $storeId);

        if ($shouldClearCache) {
            $this->cache->clear($storeId ?: null);
        }

        return $result;
    }

    public function afterDelete(ProductResource $subject, ProductResource $result): ProductResource
    {
        $this->cache->clear();
        return $result;
    }

    /**
     *  Called on mass action "Change Status"
     *  Called on "Update attributes" if `product_action_attribute.update` consumer is running
     */
    public function afterUpdateAttributes(Action $subject, Action $result, array $productIds, array $attributes, int $storeId): Action
    {
        $this->cacheHelper->handleBulkAttributeChange($productIds, $attributes, $storeId);
        return $result;
    }

    protected function isEligibleNewProduct(Product $product): bool
    {
        $storeId = $product->getStoreId();
        return $product->isObjectNew()
            && $product->getStatus() === Status::STATUS_ENABLED
            && $this->configHelper->includeNonVisibleProductsInIndex($storeId)
                || $product->isVisibleInSiteVisibility()
            && $this->configHelper->getShowOutOfStock($storeId)
                || $product->isInStock();
    }

    protected function hasEnablementChanged(array $orig, array $new): bool
    {
        $key = 'status';
        return $orig[$key] !== $new[$key];
    }

    protected function hasVisibilityChanged(array $orig, array $new, ?int $storeId = null): bool
    {
        if ($this->configHelper->includeNonVisibleProductsInIndex($storeId)) {
            return false;
        }

        $key = 'visibility';
        return $this->isVisible($orig[$key]) !== $this->isVisible($new[$key]);
    }

    /**
     * Do not rely on this data point only
     * TODO revaluate with MSI support
     */
    protected function hasStockChanged(array $orig, array $new, int $storeId): bool
    {
        if ($this->configHelper->getShowOutOfStock($storeId)) {
            return false;
        }

        $key = 'quantity_and_stock_status';
        $oldStock = $orig[$key];
        $newStock = $new[$key];
        return $this->canCompareValues($oldStock, $newStock, 'is_in_stock')
            && (bool) $oldStock['is_in_stock'] !== (bool) $newStock['is_in_stock']
            || $this->canCompareValues($oldStock, $newStock, 'qty')
            && $this->hasStock($oldStock['qty']) !== $this->hasStock($newStock['qty']);
    }

    protected function canCompareValues(array $orig, array $new, string $key): bool
    {
        return array_key_exists($key, $orig) && array_key_exists($key, $new);
    }

    protected function isVisible(int $visibility): bool
    {
        return $visibility !== Visibility::VISIBILITY_NOT_VISIBLE;
    }

    /*
     * Reduce numeric to comparable boolean
     */
    protected function hasStock(int $qty): bool
    {
        return $qty > 0;
    }
}
