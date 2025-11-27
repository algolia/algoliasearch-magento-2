<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

class PaginationMode implements OptionSourceInterface
{
    public const PAGINATION_MAGENTO_GRID = 0;
    public const PAGINATION_MAGENTO_LIST = 1;
    public const PAGINATION_CUSTOM = 2;

    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected RequestInterface $request
    ) {}

    public function toOptionArray()
    {
        $storeId = $this->request->getParam('store');
        $websiteId = $this->request->getParam('website');

        $scopeId = $storeId !== null ?
            $storeId :
            ($websiteId !== null ? $websiteId : null);

        $scope = $storeId !== null ?
            ScopeInterface::SCOPE_STORE :
            ($websiteId !== null ? ScopeInterface::SCOPE_WEBSITE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        return [
            [
                'value' => self::PAGINATION_MAGENTO_GRID,
                'label' => __(
                    'Magento Grid Pagination (%1 products per page)',
                    $this->instantSearchHelper->getMagentoGridProductsPerPage($scope, $scopeId)
                ),
            ],
            [
                'value' => self::PAGINATION_MAGENTO_LIST,
                'label' => __(
                    'Magento List Pagination (%1 products per page)',
                    $this->instantSearchHelper->getMagentoListProductsPerPage($scope, $scopeId)
                ),
            ],
            [
                'value' => self::PAGINATION_CUSTOM,
                'label' => __('Custom Pagination'),
            ],
        ];
    }
}
