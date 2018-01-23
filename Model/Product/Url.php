<?php

namespace Algolia\AlgoliaSearch\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Url as ProductUrl;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * The purpose of this class is to fix an issue during indexing where frontend URLs were using
 * the default urls in a multistore environment even when emulating a store code, due to the URL di injection.
 * E.g. if base url was www.foo.com and store url was www.bar.com, when indexing, the products for www.bar.com would be
 * indexed using the base url of www.foo.com
 */
class Url extends ProductUrl
{
    const FRONTEND_URL = 'Magento\Framework\Url';
    const BACKEND_URL = 'Magento\Backend\Model\Url';

    protected $objectManager;

    public function __construct(
        UrlFactory $urlFactory,
        StoreManagerInterface $storeManager,
        FilterManager $filter,
        SidResolverInterface $sidResolver,
        UrlFinderInterface $urlFinder,
        ObjectManagerInterface $objectManager,
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($urlFactory, $storeManager, $filter, $sidResolver, $urlFinder, $data);
    }

    /**
     * The only rewritten line in this method is the return statement
     *
     * @param Product $product
     * @param array $params
     * @return string
     */
    public function getUrl(Product $product, $params = [])
    {
        $routePath = '';
        $routeParams = $params;

        $storeId = $product->getStoreId();

        $categoryId = null;

        if (!isset($params['_ignore_category'])
            && $product->getCategoryId()
            && !$product->getData('do_not_use_category_id')) {
            $categoryId = $product->getCategoryId();
        }

        $urlDataObject = $product->getData('url_data_object');
        if ($urlDataObject !== null) {
            $requestPath = $urlDataObject->getUrlRewrite();
            $routeParams['_scope'] = $urlDataObject->getStoreId();
        } else {
            $requestPath = $product->getRequestPath();
            if (empty($requestPath) && $requestPath != false) {
                $filterData = [
                    UrlRewrite::ENTITY_ID   => $product->getId(),
                    UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
                    UrlRewrite::STORE_ID    => $storeId,
                ];
                if ($categoryId) {
                    $filterData[UrlRewrite::METADATA]['category_id'] = $categoryId;
                }
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
         * This is the only line changed from the default method.
         * For reference, the original line:
         * $this->getUrlInstance()->setScope($storeId)->getUrl($routePath, $routeParams);
         * getUrlInstance() is a private method, so a new method has been written that
         * will create a frontend Url object
         * if the store scope is not the admin scope.
         */
        return $this->getStoreScopeUrlInstance($storeId)->getUrl($routePath, $routeParams);
    }

    /**
     * If the store id passed in is admin (0), will return a Backend Url object (Default \Magento\Backend\Model\Url),
     * otherwise returns the default Url object (default \Magento\Framework\Url)
     * @param int $storeId
     * @return mixed
     */
    public function getStoreScopeUrlInstance($storeId)
    {
        if ($storeId == 0) {
            return $this->objectManager->create(self::BACKEND_URL);
        } else {
            return $this->objectManager->create(self::FRONTEND_URL);
        }
    }
}
