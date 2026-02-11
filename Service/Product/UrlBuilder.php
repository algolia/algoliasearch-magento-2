<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Magento\Backend\Model\Url as BackendUrlModel;
use Magento\Catalog\Model\Product;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class UrlBuilder
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected UrlFinderInterface $urlFinder,
        protected ObjectManagerInterface $objectManager,
        protected ?ScopeConfigInterface $scopeConfig = null
    ) {
        $this->scopeConfig = $scopeConfig ?: ObjectManager::getInstance()->get(ScopeConfigInterface::class);
    }

    /**
     * This method mimics the Magento\Catalog\Model\Product\Url::getUrl() method
     * Ensuring the right url model is called
     *
     * @param Product $product
     * @param array $params
     * @return string
     *
     * @throws NoSuchEntityException
     */
    public function getUrl(Product $product, array $params = []): string
    {
        $routePath = '';
        $routeParams = $params;

        $storeId = $product->getStoreId();

        $categoryId = null;

        if (!isset($params['_ignore_category']) && $product->getCategoryId() && !$product->getDoNotUseCategoryId()) {
            $categoryId = $product->getCategoryId();
        }

        if ($product->hasUrlDataObject()) {
            $requestPath = $product->getUrlDataObject()->getUrlRewrite();
            $routeParams['_scope'] = $product->getUrlDataObject()->getStoreId();
        } else {
            $requestPath = $product->getRequestPath();
            if (empty($requestPath) && $requestPath !== false) {
                $filterData = [
                    UrlRewrite::ENTITY_ID   => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::STORE_ID    => $storeId,
                    UrlRewrite::REDIRECT_TYPE => 0,
                ];

                $useCategories = $this->scopeConfig->getValue(
                    \Magento\Catalog\Helper\Product::XML_PATH_PRODUCT_URL_USE_CATEGORY,
                    ScopeInterface::SCOPE_STORE
                );

                $filterData[UrlRewrite::METADATA]['category_id']
                    = $categoryId && $useCategories ? $categoryId : '';

                $rewrite = $this->urlFinder->findOneByData($filterData);

                if ($rewrite) {
                    $requestPath = $rewrite->getRequestPath();
                    $product->setRequestPath($requestPath);
                } else {
                    $product->setRequestPath(false);
                }
            }
        }

        if (isset($routeParams['_scope'])) {
            $storeId = $this->storeManager->getStore($routeParams['_scope'])->getId();
        }

        // Loose (==) comparison on purpose
        if ($storeId != $this->storeManager->getStore()->getId()) {
            $routeParams['_scope_to_url'] = true;
        }

        if (!empty($requestPath)) {
            $routeParams['_direct'] = $requestPath;
        } else {
            $routePath = 'catalog/product/view';
            $routeParams['id'] = $product->getId();
            $routeParams['s'] = $product->getUrlKey();
            if ($categoryId) {
                $routeParams['category'] = $categoryId;
            }
        }

        // reset cached URL instance GET query params
        if (!isset($routeParams['_query'])) {
            $routeParams['_query'] = [];
        }

        /*
         * This is the only line changed from the original getUrl method.
         * getStoreScopeUrlInstance() is a method that will create a frontend Url object
         * if the store scope is not the admin scope.
         */
        return $this->getStoreScopeUrlInstance($storeId)->getUrl($routePath, $routeParams);
    }

    /**
     * If the store id passed in is admin (0), will return a Backend Url object (Default \Magento\Backend\Model\Url),
     * otherwise returns the default Url object (default \Magento\Framework\Url)
     *
     * @param int $storeId
     *
     * @return BackendUrlModel|Url
     */
    public function getStoreScopeUrlInstance(int $storeId): Url|BackendUrlModel
    {
        if (!$storeId) {
            return $this->objectManager->create(BackendUrlModel::class);
        }

        return $this->objectManager->create(Url::class);
    }
}
