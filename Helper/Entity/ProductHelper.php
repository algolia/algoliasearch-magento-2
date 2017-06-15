<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\Currency;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Directory\Helper\Data as CurrencyDirectory;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data;

class ProductHelper extends BaseHelper
{
    /**
     * @var Image $imageHelper
     */
    protected $imageHelper;

    protected static $_productAttributes;
    protected static $_currencies;
    protected static $debug = 0;

    protected static $_predefinedProductAttributes = [
        'name',
        'url_key',
        'image',
        'small_image',
        'thumbnail',
        'msrp_enabled', // Needed to handle MSRP behavior
    ];

    protected static $createdAttributes = [
        'path',
        'categories',
        'categories_without_path',
        'ordered_qty',
        'total_ordered',
        'stock_qty',
        'rating_summary',
        'media_gallery',
        'in_stock',
    ];

    public function __construct(
        Config $eavConfig,
        ConfigHelper $configHelper,
        AlgoliaHelper $algoliaHelper,
        Logger $logger,
        StoreManagerInterface $storeManager,
        ManagerInterface $eventManager,
        Visibility $visibility,
        Stock $stock,
        Data $taxHelper,
        StockRegistryInterface $stockRegistry,
        CurrencyDirectory $currencyDirectory,
        CurrencyHelper $currencyHelper,
        ObjectManagerInterface $objectManager,
        CatalogHelper $catalogHelper,
        ResourceConnection $queryResource,
        CurrencyHelper $currencyManager,
        FilterProvider $filterProvider,
        PriceCurrencyInterface $priceCurrency,
        Rule $rule
    ) {
        parent::__construct($eavConfig, $configHelper, $algoliaHelper, $logger, $storeManager,
            $eventManager, $visibility, $stock, $taxHelper, $stockRegistry, $currencyDirectory,
            $currencyHelper, $objectManager, $catalogHelper, $queryResource, $currencyManager,
            $filterProvider, $priceCurrency, $rule);

        $this->imageHelper = $this->objectManager->create(
            'Algolia\AlgoliaSearch\Helper\Image',
            [
                'options' =>[
                    'shouldRemovePubDir' => $this->config->shouldRemovePubDirectory(),
                ]
            ]);
    }

    protected function getIndexNameSuffix()
    {
        return '_products';
    }

    public function getAllAttributes($add_empty_row = false)
    {
        if (is_null(self::$_productAttributes)) {
            self::$_productAttributes = [];

            $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_product');

            $productAttributes = array_merge([
                'name',
                'path',
                'categories',
                'categories_without_path',
                'description',
                'ordered_qty',
                'total_ordered',
                'stock_qty',
                'rating_summary',
                'media_gallery',
                'in_stock',
            ], $allAttributes);

            $excludedAttributes = [
                'all_children', 'available_sort_by', 'children', 'children_count', 'custom_apply_to_products',
                'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings',
                'default_sort_by', 'display_mode', 'filter_price_range', 'global_position', 'image', 'include_in_menu', 'is_active',
                'is_always_include_in_menu', 'is_anchor', 'landing_page', 'level', 'lower_cms_block',
                'page_layout', 'path_in_store', 'position', 'small_image', 'thumbnail', 'url_key', 'url_path',
                'visible_in_menu', 'quantity_and_stock_status', ];

            $productAttributes = array_diff($productAttributes, $excludedAttributes);

            foreach ($productAttributes as $attributeCode) {
                self::$_productAttributes[$attributeCode] = $this->eavConfig->getAttribute('catalog_product', $attributeCode)->getFrontendLabel();
            }
        }

        $attributes = self::$_productAttributes;

        if ($add_empty_row === true) {
            $attributes[''] = '';
        }

        uksort($attributes, function ($a, $b) {
            return strcmp($a, $b);
        });

        return $attributes;
    }

    public function isAttributeEnabled($additionalAttributes, $attr_name)
    {
        foreach ($additionalAttributes as $attr) {
            if ($attr['attribute'] === $attr_name) {
                return true;
            }
        }

        return false;
    }

    public function getProductCollectionQuery($storeId, $productIds = null, $only_visible = true)
    {
        /** @var $products \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $products = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');

        $products = $products->setStoreId($storeId)
            ->addStoreFilter($storeId);

        if ($only_visible) {
            $products = $products->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSearchIds()]);
        }

        if ($this->config->getShowOutOfStock($storeId) === false) {
            $this->stock->addInStockFilterToCollection($products);
        }

        /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $products = $products->addFinalPrice()
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

        $additionalAttr = $this->getAdditionalAttributes($storeId);

        foreach ($additionalAttr as &$attr) {
            $attr = $attr['attribute'];
        }

        $attrs = array_merge(static::$_predefinedProductAttributes, $additionalAttr);
        $attrs = array_diff($attrs, static::$createdAttributes);

        $products = $products->addAttributeToSelect(array_values($attrs));

        if ($productIds && count($productIds) > 0) {
            $products = $products->addAttributeToFilter('entity_id', ['in' => $productIds]);
        }

        $this->eventManager->dispatch('algolia_rebuild_store_product_index_collection_load_before', ['store' => $storeId, 'collection' => $products]);

        return $products;
    }

    public function getAdditionalAttributes($storeId = null)
    {
        return $this->config->getProductAdditionalAttributes($storeId);
    }

    public function setSettings($storeId, $saveToTmpIndicesToo = false)
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];
        $attributesForFaceting = [];

        foreach ($this->getAdditionalAttributes() as $attribute) {
            if ($attribute['searchable'] == '1') {
                if ($attribute['order'] == 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] != '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }

            if ($attribute['attribute'] == 'categories') {
                $searchableAttributes[] = $attribute['order'] == 'ordered' ? 'categories_without_path' : 'unordered(categories_without_path)';
            }
        }

        $customRankings = $this->config->getProductCustomRanking($storeId);

        $customRankingsArr = [];

        $facets = $this->config->getFacets();

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->config->isCustomerGroupsEnabled($storeId)) {
                        $groupCollection = $this->objectManager->create('Magento\Customer\Model\ResourceModel\Group\Collection');

                        foreach ($groupCollection as $group) {
                            $group_id = (int) $group->getData('customer_group_id');

                            $attributesForFaceting[] = 'price.' . $currency_code . '.group_' . $group_id;
                        }
                    }

                    $attributesForFaceting[] = $facet['attribute'];
                }
            } else {
                $attributesForFaceting[] = $facet['attribute'];
            }
        }

        foreach ($customRankings as $ranking) {
            $customRankingsArr[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        if ($this->config->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'customRanking'           => $customRankingsArr,
            'unretrievableAttributes' => $unretrievableAttributes,
            'attributesForFaceting'   => $attributesForFaceting,
            'maxValuesPerFacet'       => (int) $this->config->getMaxValuesPerFacet($storeId),
            'removeWordsIfNoResults'  => $this->config->getRemoveWordsIfNoResult($storeId),
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch('algolia_index_settings_prepare', [
            'store_id'       => $storeId,
            'index_settings' => $transport,
        ]);
        $indexSettings = $transport->getData();

        $mergeSettings = $this->algoliaHelper->mergeSettings($this->getIndexName($storeId), $indexSettings);

        $this->algoliaHelper->setSettings($this->getIndexName($storeId), $mergeSettings);
        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->setSettings($this->getIndexName($storeId, true), $mergeSettings);
        }

        /*
         * Handle replicas
         */
        $isInstantSearchEnabled = (bool) $this->config->isInstantEnabled($storeId);
        $sortingIndices = $this->config->getSortingIndices($storeId);

        if ($isInstantSearchEnabled === true && count($sortingIndices) > 0) {
            $replicas = array_values(array_map(function($sortingIndex) {
                return $sortingIndex['name'];
            }, $sortingIndices));

            $this->algoliaHelper->setSettings($this->getIndexName($storeId), ['replicas' => $replicas]);

            foreach ($sortingIndices as $values) {
                $mergeSettings['ranking'] = $values['ranking'];
                $this->algoliaHelper->setSettings($values['name'], $mergeSettings);
            }
        }

        if ($this->config->isEnabledSynonyms($storeId) === true) {
            if ($synonymsFile = $this->config->getSynonymsFile($storeId)) {
                $synonymsToSet = json_decode(file_get_contents($synonymsFile));
            } else {
                $synonymsToSet = [];

                $synonyms = $this->config->getSynonyms($storeId);
                foreach ($synonyms as $objectID => $synonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'synonym',
                        'synonyms' => $this->explodeSynonyms($synonym['synonyms']),
                    ];
                }

                $onewaySynonyms = $this->config->getOnewaySynonyms($storeId);
                foreach ($onewaySynonyms as $objectID => $onewaySynonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'oneWaySynonym',
                        'input' => $onewaySynonym['input'],
                        'synonyms' => $this->explodeSynonyms($onewaySynonym['synonyms']),
                    ];
                }
            }

            $this->algoliaHelper->setSynonyms($this->getIndexName($storeId, $saveToTmpIndicesToo), $synonymsToSet);
        } elseif ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->copySynonyms($this->getIndexName($storeId), $this->getIndexName($storeId, $saveToTmpIndicesToo));
        }
    }

    protected function getFields($store)
    {
        if ($this->taxHelper->getPriceDisplayType($store) == TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX) {
            return ['price' => false];
        }

        if ($this->taxHelper->getPriceDisplayType($store) == TaxConfig::DISPLAY_TYPE_INCLUDING_TAX) {
            return ['price' => true];
        }

        return ['price' => false, 'price_with_tax' => true];
    }

    protected function handlePrice(Product &$product, $subProducts, &$customData)
    {
        $store = $product->getStore();
        $type = $product->getTypeId();

        $fields = $this->getFields($store);

        $areCustomersGroupsEnabled = $this->config->isCustomerGroupsEnabled($product->getStoreId());

        $currencies = $store->getAvailableCurrencyCodes();
        $baseCurrencyCode = $store->getBaseCurrencyCode();

        $groups = [];

        if ($areCustomersGroupsEnabled) {
            $groups = $this->objectManager->create('Magento\Customer\Model\ResourceModel\Group\Collection');
        }

        foreach ($fields as $field => $withTax) {
            $customData[$field] = [];

            foreach ($currencies as $currencyCode) {
                $customData[$field][$currencyCode] = [];

                $price = $product->getPrice();
                if ($currencyCode !== $baseCurrencyCode) {
                    $price = $this->priceCurrency->convert($price, $store, $currencyCode);
                }

                $price = (double) $this->catalogHelper->getTaxPrice($product, $price, $withTax, null, null, null, $product->getStore(), null);

                $customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($price);
                $customData[$field][$currencyCode]['default_formated'] = $this->priceCurrency->format($price, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);

                $specialPrices = [];
                $specialPrices[] = (double) $this->rule->getRulePrice(new \DateTime(), $store->getWebsiteId(), 0, $product->getId()); // The price with applied catalog rules
                $specialPrices[] = (double) $product->getFinalPrice(); // The product's special price

                $specialPrices = array_filter($specialPrices, function($price) {
                    return $price > 0;
                });

                $specialPrice = false;
                if (!empty($specialPrices)) {
                    $specialPrice = min($specialPrices);
                }

                if ($specialPrice) {
                    if ($currencyCode !== $baseCurrencyCode) {
                        $specialPrice = $this->priceCurrency->convert($specialPrice, $store, $currencyCode);
                        $specialPrice = $this->priceCurrency->round($specialPrice);
                    }

                    $specialPrice = (double)$this->catalogHelper->getTaxPrice($product, $specialPrice, $withTax, null,
                        null, null, $product->getStore(), null);
                }

                if ($areCustomersGroupsEnabled) {
                    foreach ($groups as $group) {
                        $groupId = (int) $group->getData('customer_group_id');

                        $product->setCustomerGroupId($groupId);

                        $discountedPrice = (double) $product->getPriceModel()->getFinalPrice(1, $product);
                        if ($currencyCode !== $baseCurrencyCode) {
                            $discountedPrice = $this->priceCurrency->convert($discountedPrice, $store, $currencyCode);
                        }

                        if ($discountedPrice !== false) {
                            $customData[$field][$currencyCode]['group_' . $groupId] = (double) $this->catalogHelper->getTaxPrice($product, $discountedPrice, $withTax, null, null, null, $product->getStore(), null);
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $this->priceCurrency->format($customData[$field][$currencyCode]['group_' . $groupId], false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);
                        } else {
                            $customData[$field][$currencyCode]['group_' . $groupId] = $customData[$field][$currencyCode]['default'];
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $customData[$field][$currencyCode]['default_formated'];
                        }
                    }

                    $product->setCustomerGroupId(null);
                }

                $customData[$field][$currencyCode]['special_from_date'] = strtotime($product->getSpecialFromDate());
                $customData[$field][$currencyCode]['special_to_date'] = strtotime($product->getSpecialToDate());

                if ($areCustomersGroupsEnabled) {
                    foreach ($groups as $group) {
                        $groupId = (int) $group->getData('customer_group_id');

                        if ($specialPrice && $specialPrice < $customData[$field][$currencyCode]['group_' . $groupId]) {
                            $customData[$field][$currencyCode]['group_' . $groupId] = $specialPrice;
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $this->priceCurrency->format($specialPrice, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);
                        }
                    }
                } else {
                    if ($specialPrice && $specialPrice < $customData[$field][$currencyCode]['default']) {
                        $customData[$field][$currencyCode]['default_original_formated'] = $customData[$field][$currencyCode]['default_formated'];

                        $customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($specialPrice);
                        $customData[$field][$currencyCode]['default_formated'] = $this->priceCurrency->format($specialPrice, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);
                    }
                }

                if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
                    $min = PHP_INT_MAX;
                    $max = 0;

                    if ($type == 'bundle') {
                        list($min, $max) = $product->getPriceModel()->getTotalPrices($product, null, $withTax, true);
                    }

                    if ($type == 'grouped' || $type == 'configurable') {
                        if (count($subProducts) > 0) {
                            /** @var Product $subProduct */
                            foreach ($subProducts as $subProduct) {
                                $price = (double) $this->catalogHelper->getTaxPrice($product, $subProduct->getFinalPrice(), $withTax, null, null, null, $product->getStore(), null);

                                $min = min($min, $price);
                                $max = max($max, $price);
                            }
                        } else {
                            $min = $max;
                        } // avoid to have PHP_INT_MAX in case of no subproducts (Corner case of visibility and stock options)
                    }

                    if ($min != $max) {
                        if ($currencyCode !== $baseCurrencyCode) {
                            $min = $this->priceCurrency->convert($min, $store, $currencyCode);
                        }

                        if ($currencyCode !== $baseCurrencyCode) {
                            $max = $this->priceCurrency->convert($max, $store, $currencyCode);
                        }

                        $dashedFormat =
                            $this->priceCurrency->format($min, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode)
                            . ' - ' .
                            $this->priceCurrency->format($max, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);

                        if (isset($customData[$field][$currencyCode]['default_original_formated']) === false || $min <= $customData[$field][$currencyCode]['default']) {
                            $customData[$field][$currencyCode]['default_formated'] = $dashedFormat;

                            //// Do not keep special price that is already taken into account in min max
                            unset($customData['price']['special_from_date']);
                            unset($customData['price']['special_to_date']);
                            unset($customData['price']['default_original_formated']);

                            $customData[$field][$currencyCode]['default'] = 0; // will be reset just after
                        }

                        if ($areCustomersGroupsEnabled) {
                            foreach ($groups as $group) {
                                $groupId = (int) $group->getData('customer_group_id');

                                if ($min != $max && $min <= $customData[$field][$currencyCode]['group_' . $groupId]) {
                                    $customData[$field][$currencyCode]['group_' . $groupId] = 0;
                                    $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                                }
                            }
                        }
                    }

                    if ($customData[$field][$currencyCode]['default'] == 0) {
                        $customData[$field][$currencyCode]['default'] = $min;

                        if ($min === $max) {
                            if ($currencyCode !== $baseCurrencyCode) {
                                $min = $this->priceCurrency->convert($min, $store, $currencyCode);
                            }

                            $customData[$field][$currencyCode]['default'] = $min;
                            $customData[$field][$currencyCode]['default_formated'] = $this->priceCurrency->format($min, false, PriceCurrencyInterface::DEFAULT_PRECISION, $store, $currencyCode);
                        }
                    }

                    if ($areCustomersGroupsEnabled) {
                        foreach ($groups as $group) {
                            $groupId = (int) $group->getData('customer_group_id');

                            if ($customData[$field][$currencyCode]['group_' . $groupId] == 0) {
                                $customData[$field][$currencyCode]['group_' . $groupId] = $min;

                                if ($min === $max) {
                                    $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $customData[$field][$currencyCode]['default_formated'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function getAllCategories($category_ids)
    {
        static $categories = null;

        if ($categories === null) {
            $categoriesData = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');
            $categoriesData
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('include_in_menu', '1')
                ->addFieldToFilter('level', ['gt' => 1])
                ->addIsActiveFilter();

            $categories = [];

            foreach ($categoriesData as $category) {
                $categories[$category->getId()] = $category;
            }
        }

        $selected_categories = [];

        foreach ($category_ids as $id) {
            if (isset($categories[$id])) {
                $selected_categories[] = $categories[$id];
            }
        }

        return $selected_categories;
    }

    public function getObject(Product $product)
    {
        $type = $product->getTypeId();
        $this->logger->start('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($product->getStoreId()));
        $defaultData = [];

        $transport = new DataObject($defaultData);
        $this->eventManager->dispatch('algolia_product_index_before', ['product' => $product, 'custom_data' => $transport]);

        $defaultData = $transport->getData();

        $visibility = (int) $product->getVisibility();

        $visibleInCatalog = $this->visibility->getVisibleInCatalogIds();
        $visibleInSearch = $this->visibility->getVisibleInSearchIds();

        $urlParams = [
            '_secure' => $this->config->useSecureUrlsInFrontend($product->getStoreId()),
        ];

        $customData = [
            'objectID'           => $product->getId(),
            'name'               => $product->getName(),
            'url'                => $product->getUrlModel()->getUrl($product, $urlParams),
            'visibility_search'  => (int) (in_array($visibility, $visibleInSearch)),
            'visibility_catalog' => (int) (in_array($visibility, $visibleInCatalog)),
        ];

        $additionalAttributes = $this->getAdditionalAttributes($product->getStoreId());
        $groups = null;

        if ($this->isAttributeEnabled($additionalAttributes, 'description')) {
            $customData['description'] = $product->getDescription();
        }

        $categories = [];
        $categories_with_path = [];

        $_categoryIds = $product->getCategoryIds();

        if (is_array($_categoryIds) && count($_categoryIds) > 0) {
            $categoryCollection = $this->getAllCategories($_categoryIds);
            $rootCat = $this->storeManager->getStore($product->getStoreId())->getRootCategoryId();

            foreach ($categoryCollection as $category) {
                // Check and skip all categories that is not
                // in the path of the current store.
                $path = $category->getPath();
                $path_parts = explode('/', $path);
                if (isset($path_parts[1]) && $path_parts[1] != $rootCat) {
                    continue;
                }

                $categoryName = $category->getName();

                if ($categoryName) {
                    $categories[] = $categoryName;
                }

                $category->getUrlInstance()->setStore($product->getStoreId());
                $path = [];

                foreach ($category->getPathIds() as $treeCategoryId) {
                    $name = $this->getCategoryName($treeCategoryId, $product->getStoreId());
                    if ($name) {
                        $path[] = $name;
                    }
                }

                $categories_with_path[] = $path;
            }
        }

        foreach ($categories_with_path as $result) {
            for ($i = count($result) - 1; $i > 0; $i--) {
                $categories_with_path[] = array_slice($result, 0, $i);
            }
        }

        $categories_with_path = array_intersect_key($categories_with_path, array_unique(array_map('serialize', $categories_with_path)));

        $categories_hierarchical = [];

        $level_name = 'level';

        foreach ($categories_with_path as $category) {
            for ($i = 0; $i < count($category); $i++) {
                if (isset($categories_hierarchical[$level_name . $i]) === false) {
                    $categories_hierarchical[$level_name . $i] = [];
                }

                $categories_hierarchical[$level_name . $i][] = implode(' /// ', array_slice($category, 0, $i + 1));
            }
        }

        foreach ($categories_hierarchical as &$level) {
            $level = array_values(array_unique($level));
        }

        foreach ($categories_with_path as &$category) {
            $category = implode(' /// ', $category);
        }

        $customData['categories'] = $categories_hierarchical;

        $customData['categories_without_path'] = $categories;

        $customData = $this->addImageData($customData, $product, $additionalAttributes);

        $sub_products = [];
        $ids = null;

        if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
            if ($type == 'bundle') {
                $ids = [];

                $selection = $product->getTypeInstance(true)->getSelectionsCollection($product->getTypeInstance(true)->getOptionsIds($product), $product);

                foreach ($selection as $option) {
                    $ids[] = $option->getProductId();
                }
            }

            if ($type == 'configurable' || $type == 'grouped') {
                $ids = $product->getTypeInstance(true)->getChildrenIds($product->getId());
                $ids = call_user_func_array('array_merge', $ids);
            }

            if (count($ids)) {
                $sub_products = $this->getProductCollectionQuery($product->getStoreId(), $ids, false)->load();
            }
        }

        if (false === isset($defaultData['in_stock'])) {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());

            $customData['in_stock'] = $stockItem && (int) $stockItem->getIsInStock();
        }

        // skip default calculation if we have provided these attributes via the observer in $defaultData
        if (false === isset($defaultData['ordered_qty']) && $this->isAttributeEnabled($additionalAttributes, 'ordered_qty')) {
            $customData['ordered_qty'] = (int) $product->getOrderedQty();
        }

        if (false === isset($defaultData['total_ordered']) && $this->isAttributeEnabled($additionalAttributes, 'total_ordered')) {
            $customData['total_ordered'] = (int) $product->getTotalOrdered();
        }

        if (false === isset($defaultData['stock_qty']) && $this->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $customData['stock_qty'] = (int) $product->getStockQty();
        }

        if ($this->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
            $customData['rating_summary'] = (int) $product->getRatingSummary();
        }

        foreach ($additionalAttributes as $attribute) {
            if (isset($customData[$attribute['attribute']])) {
                continue;
            }

            $value = $product->getData($attribute['attribute']);

            $attribute_resource = $product->getResource()->getAttribute($attribute['attribute']);

            if ($attribute_resource) {
                $attribute_resource = $attribute_resource->setStoreId($product->getStoreId());

                if ($value === null) {
                    /* Get values as array in children */
                    if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
                        $values = [];

                        $all_sub_products_out_of_stock = true;

                        /** @var Product $sub_product */
                        foreach ($sub_products as $sub_product) {
                            $isInStock = (int) $this->stockRegistry->getStockItem($sub_product->getId())->getIsInStock();

                            if ($isInStock == false && $this->config->indexOutOfStockOptions($product->getStoreId()) == false) {
                                continue;
                            }

                            $all_sub_products_out_of_stock = false;

                            $value = $sub_product->getData($attribute['attribute']);

                            if ($value) {
                                $value_text = $sub_product->getAttributeText($attribute['attribute']);

                                if ($value_text) {
                                    if (is_array($value_text)) {
                                        foreach ($value_text as $value_text_elt) {
                                            $values[] = $value_text_elt;
                                        }
                                    } else {
                                        $values[] = $value_text;
                                    }
                                } else {
                                    $values[] = $attribute_resource->getFrontend()->getValue($sub_product);
                                }
                            }
                        }

                        if (is_array($values) && count($values) > 0) {
                            $customData[$attribute['attribute']] = array_values(array_unique($values));
                        }

                        if ($customData['in_stock'] && $all_sub_products_out_of_stock) {
                            // Set main product out of stock if all
                            // sub-products is out of stock.
                            $customData['in_stock'] = 0;
                        }
                    }
                } else {
                    $value_text = null;

                    if (!is_array($value)) {
                        $value_text = $product->getAttributeText($attribute['attribute']);
                    }

                    if ($value_text) {
                        $value = $value_text;
                    } else {
                        $attribute_resource = $attribute_resource->setStoreId($product->getStoreId());
                        $value = $attribute_resource->getFrontend()->getValue($product);
                    }

                    if ($value) {
                        $customData[$attribute['attribute']] = $value;
                    }
                }
            }
        }

        $this->handlePrice($product, $sub_products, $customData);

        $customData['type_id'] = $type;

        $transport = new DataObject($customData);
        $this->eventManager->dispatch('algolia_subproducts_index', ['custom_data' => $transport, 'sub_products' => $sub_products]);
        $customData = $transport->getData();

        $customData = array_merge($customData, $defaultData);

        $this->castProductObject($customData);

        $this->logger->stop('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($product->getStoreId()));

        return $customData;
    }

    protected function addImageData(array $customData, $product, $additionalAttributes)
    {
        if (false === isset($defaultData['thumbnail_url'])) {
            $customData['thumbnail_url'] = $this->imageHelper
                ->init($product, 'thumbnail')
                ->resize(75, 75)
                ->getUrl();
        }

        if (false === isset($defaultData['image_url'])) {
            $this->imageHelper
                ->init($product, $this->config->getImageType())
                ->resize($this->config->getImageWidth(), $this->config->getImageHeight());

            $customData['image_url'] = $this->imageHelper->getUrl();

            if ($this->isAttributeEnabled($additionalAttributes, 'media_gallery')) {
                $product->load('media_gallery');

                $customData['media_gallery'] = [];

                $images = $product->getMediaGalleryImages();
                if ($images) {
                    foreach ($images as $image) {
                        $url = $image->getUrl();
                        $url = $this->imageHelper->removeProtocol($url);
                        $url = $this->imageHelper->removeDoubleSlashes($url);

                        $customData['media_gallery'][] = $url;
                    }
                }
            }
        }

        return $customData;
    }

    private function explodeSynonyms($synonyms)
    {
        return array_map('trim', explode(',', $synonyms));
    }
}
