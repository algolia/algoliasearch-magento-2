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
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data;

class ProductHelper
{
    private $eavConfig;
    private $configHelper;
    private $algoliaHelper;
    private $logger;
    private $storeManager;
    private $eventManager;
    private $visibility;
    private $stockHelper;
    private $taxHelper;
    private $stockRegistry;
    private $objectManager;
    private $catalogHelper;
    private $currencyManager;
    private $priceCurrency;
    private $rule;
    private $categoryHelper;

    /** @var Image $imageHelper */
    private $imageHelper;

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
        Stock $stockHelper,
        Data $taxHelper,
        StockRegistryInterface $stockRegistry,
        ObjectManagerInterface $objectManager,
        CatalogHelper $catalogHelper,
        CurrencyHelper $currencyManager,
        PriceCurrencyInterface $priceCurrency,
        Rule $rule,
        CategoryHelper $categoryHelper
    ) {
        $this->eavConfig = $eavConfig;
        $this->configHelper = $configHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->visibility = $visibility;
        $this->stockHelper = $stockHelper;
        $this->taxHelper = $taxHelper;
        $this->stockRegistry = $stockRegistry;
        $this->objectManager = $objectManager;
        $this->catalogHelper = $catalogHelper;
        $this->currencyManager = $currencyManager;
        $this->priceCurrency = $priceCurrency;
        $this->rule = $rule;
        $this->categoryHelper = $categoryHelper;

        $this->imageHelper = $this->objectManager->create(
            'Algolia\AlgoliaSearch\Helper\Image',
            [
                'options' =>[
                    'shouldRemovePubDir' => $this->configHelper->shouldRemovePubDirectory(),
                ]
            ]
        );
    }

    public function getIndexNameSuffix()
    {
        return '_products';
    }

    public function getAllAttributes($addEmptyRow = false)
    {
        if (self::$_productAttributes === null) {
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
                'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update',
                'custom_use_parent_settings', 'default_sort_by', 'display_mode', 'filter_price_range',
                'global_position', 'image', 'include_in_menu', 'is_active', 'is_always_include_in_menu', 'is_anchor',
                'landing_page', 'level', 'lower_cms_block', 'page_layout', 'path_in_store', 'position', 'small_image',
                'thumbnail', 'url_key', 'url_path', 'visible_in_menu', 'quantity_and_stock_status',
            ];

            $productAttributes = array_diff($productAttributes, $excludedAttributes);

            foreach ($productAttributes as $attributeCode) {
                self::$_productAttributes[$attributeCode] = $this->eavConfig
                    ->getAttribute('catalog_product', $attributeCode)
                    ->getFrontendLabel();
            }
        }

        $attributes = self::$_productAttributes;

        if ($addEmptyRow === true) {
            $attributes[''] = '';
        }

        uksort($attributes, function ($a, $b) {
            return strcmp($a, $b);
        });

        return $attributes;
    }

    public function isAttributeEnabled($additionalAttributes, $attributeName)
    {
        foreach ($additionalAttributes as $attr) {
            if ($attr['attribute'] === $attributeName) {
                return true;
            }
        }

        return false;
    }

    public function getProductCollectionQuery($storeId, $productIds = null, $onlyVisible = true)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');

        $products = $products
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->distinct(true);

        if ($onlyVisible) {
            $products = $products->addAttributeToFilter(
                'visibility',
                ['in' => $this->visibility->getVisibleInSiteIds()]
            );
        }

        if ($onlyVisible && $this->configHelper->getShowOutOfStock($storeId) === false) {
            $this->stockHelper->addInStockFilterToCollection($products);
        }

        /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $products = $products->addFinalPrice()
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToFilter(
                'status',
                ['=' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED]
            );

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

        $this->eventManager->dispatch( // Only for backward compatibility
            'algolia_rebuild_store_product_index_collection_load_before',
            ['store' => $storeId, 'collection' => $products]
        );
        $this->eventManager->dispatch(
            'algolia_after_products_collection_build',
            ['store' => $storeId, 'collection' => $products]
        );

        return $products;
    }

    public function getAdditionalAttributes($storeId = null)
    {
        return $this->configHelper->getProductAdditionalAttributes($storeId);
    }

    public function setSettings($indexName, $indexNameTmp, $storeId, $saveToTmpIndicesToo = false)
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];
        $attributesForFaceting = [];

        foreach ($this->getAdditionalAttributes() as $attribute) {
            if ($attribute['searchable'] == '1') {
                if (!isset($attribute['order']) || $attribute['order'] == 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] != '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }

            if ($attribute['attribute'] == 'categories') {
                $searchableAttributes[] = (isset($attribute['order']) && $attribute['order'] == 'ordered') ?
                    'categories_without_path' : 'unordered(categories_without_path)';
            }
        }

        $customRankings = $this->configHelper->getProductCustomRanking($storeId);

        $customRankingsArr = [];

        $facets = $this->configHelper->getFacets();

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
                        $groupCollection = $this->objectManager
                            ->create('Magento\Customer\Model\ResourceModel\Group\Collection');

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

        if ($this->configHelper->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'customRanking'           => $customRankingsArr,
            'unretrievableAttributes' => $unretrievableAttributes,
            'attributesForFaceting'   => $attributesForFaceting,
            'maxValuesPerFacet'       => (int) $this->configHelper->getMaxValuesPerFacet($storeId),
            'removeWordsIfNoResults'  => $this->configHelper->getRemoveWordsIfNoResult($storeId),
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch( // Only for backward compatibility
            'algolia_index_settings_prepare',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $this->eventManager->dispatch(
            'algolia_products_index_before_set_settings',
            [
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]
        );
        $indexSettings = $transport->getData();

        $this->algoliaHelper->setSettings($indexName, $indexSettings, false, true);
        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->setSettings($indexNameTmp, $indexSettings, false, true);
        }

        /*
         * Handle replicas
         */
        $isInstantSearchEnabled = (bool) $this->configHelper->isInstantEnabled($storeId);
        $sortingIndices = $this->configHelper->getSortingIndices($indexName, $storeId);

        if ($isInstantSearchEnabled === true && count($sortingIndices) > 0) {
            $replicas = array_values(array_map(function ($sortingIndex) {
                return $sortingIndex['name'];
            }, $sortingIndices));

            $this->algoliaHelper->setSettings($indexName, ['replicas' => $replicas]);

            foreach ($sortingIndices as $values) {
                $indexSettings['ranking'] = $values['ranking'];
                $this->algoliaHelper->setSettings($values['name'], $indexSettings, false, true);
            }
        }

        if ($this->configHelper->isEnabledSynonyms($storeId) === true) {
            if ($synonymsFile = $this->configHelper->getSynonymsFile($storeId)) {
                $synonymsToSet = json_decode(file_get_contents($synonymsFile));
            } else {
                $synonymsToSet = [];

                $synonyms = $this->configHelper->getSynonyms($storeId);
                foreach ($synonyms as $objectID => $synonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'synonym',
                        'synonyms' => $this->explodeSynonyms($synonym['synonyms']),
                    ];
                }

                $onewaySynonyms = $this->configHelper->getOnewaySynonyms($storeId);
                foreach ($onewaySynonyms as $objectID => $onewaySynonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'oneWaySynonym',
                        'input' => $onewaySynonym['input'],
                        'synonyms' => $this->explodeSynonyms($onewaySynonym['synonyms']),
                    ];
                }
            }

            $this->algoliaHelper->setSynonyms($saveToTmpIndicesToo ? $indexNameTmp : $indexName, $synonymsToSet);
        } elseif ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->copySynonyms($indexName, $indexNameTmp);
        }

        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->copyQueryRules($indexName, $indexNameTmp);
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

        $areCustomersGroupsEnabled = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId());

        $currencies = $store->getAvailableCurrencyCodes();
        $baseCurrencyCode = $store->getBaseCurrencyCode();

        $groups = $this->objectManager->create('Magento\Customer\Model\ResourceModel\Group\Collection');

        if (!$areCustomersGroupsEnabled) {
            $groups->addFieldToFilter('main_table.customer_group_id', 0);
        }

        foreach ($fields as $field => $withTax) {
            $customData[$field] = [];

            foreach ($currencies as $currencyCode) {
                $customData[$field][$currencyCode] = [];

                $price = $product->getPrice();
                if ($currencyCode !== $baseCurrencyCode) {
                    $price = $this->priceCurrency->convert($price, $store, $currencyCode);
                }

                $price = (double) $this->catalogHelper
                    ->getTaxPrice($product, $price, $withTax, null, null, null, $product->getStore(), null);

                $customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($price);
                $customData[$field][$currencyCode]['default_formated'] = $this->priceCurrency->format(
                    $price,
                    false,
                    PriceCurrencyInterface::DEFAULT_PRECISION,
                    $store,
                    $currencyCode
                );

                $specialPrices   = [];
                $specialPrice    = [];
                foreach ($groups as $group) {
                    $groupId = (int) $group->getData('customer_group_id');
                    $specialPrices[$groupId] = [];
                    $specialPrices[$groupId][] = (double) $this->rule->getRulePrice(
                        new \DateTime(),
                        $store->getWebsiteId(),
                        $groupId,
                        $product->getId()
                    ); // The price with applied catalog rules
                    $specialPrices[$groupId][] = $product->getFinalPrice(); // The product's special price

                    $specialPrices[$groupId] = array_filter($specialPrices[$groupId], function ($price) {
                        return $price > 0;
                    });

                    $specialPrice[$groupId] = false;
                    if (!empty($specialPrices[$groupId])) {
                        $specialPrice[$groupId] = min($specialPrices[$groupId]);
                    }

                    if ($specialPrice[$groupId]) {
                        if ($currencyCode !== $baseCurrencyCode) {
                            $specialPrice[$groupId] = $this->priceCurrency->convert(
                                $specialPrice[$groupId],
                                $store,
                                $currencyCode
                            );
                            $specialPrice[$groupId] = $this->priceCurrency->round($specialPrice[$groupId]);
                        }

                        $specialPrice[$groupId] = (double) $this->catalogHelper->getTaxPrice(
                            $product,
                            $specialPrice[$groupId],
                            $withTax,
                            null,
                            null,
                            null,
                            $product->getStore(),
                            null
                        );
                    }
                }

                if ($areCustomersGroupsEnabled) {
                    /** @var \Magento\Customer\Model\Group $group */
                    foreach ($groups as $group) {
                        $groupId = (int) $group->getData('customer_group_id');

                        $product->setData('customer_group_id', $groupId);

                        $discountedPrice = $product->getPriceModel()->getFinalPrice(1, $product);
                        if ($currencyCode !== $baseCurrencyCode) {
                            $discountedPrice = $this->priceCurrency->convert($discountedPrice, $store, $currencyCode);
                        }

                        if ($discountedPrice !== false) {
                            $taxPrice = (double) $this->catalogHelper->getTaxPrice(
                                $product,
                                $discountedPrice,
                                $withTax,
                                null,
                                null,
                                null,
                                $product->getStore(),
                                null
                            );

                            $customData[$field][$currencyCode]['group_' . $groupId] = $taxPrice;

                            $formated = $this->priceCurrency->format(
                                $customData[$field][$currencyCode]['group_' . $groupId],
                                false,
                                PriceCurrencyInterface::DEFAULT_PRECISION,
                                $store,
                                $currencyCode
                            );
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $formated;

                            if ($customData[$field][$currencyCode]['default'] >
                                $customData[$field][$currencyCode]['group_' . $groupId]) {
                                $original = $customData[$field][$currencyCode]['default_formated'];
                                $customData[$field][$currencyCode]['group_'.$groupId.'_original_formated'] = $original;
                            }
                        } else {
                            $default = $customData[$field][$currencyCode]['default'];
                            $customData[$field][$currencyCode]['group_' . $groupId] = $default;

                            $defaultFormated = $customData[$field][$currencyCode]['default_formated'];
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $defaultFormated;
                        }
                    }

                    $product->setData('customer_group_id', null);
                }

                $customData[$field][$currencyCode]['special_from_date'] = strtotime($product->getSpecialFromDate());
                $customData[$field][$currencyCode]['special_to_date'] = strtotime($product->getSpecialToDate());

                if ($areCustomersGroupsEnabled) {
                    foreach ($groups as $group) {
                        $groupId = (int) $group->getData('customer_group_id');

                        if ($specialPrice[$groupId]
                            && $specialPrice[$groupId] < $customData[$field][$currencyCode]['group_' . $groupId]) {
                            $customData[$field][$currencyCode]['group_' . $groupId] = $specialPrice[$groupId];

                            $formated = $this->priceCurrency->format(
                                $specialPrice[$groupId],
                                false,
                                PriceCurrencyInterface::DEFAULT_PRECISION,
                                $store,
                                $currencyCode
                            );
                            $customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $formated;

                            if ($customData[$field][$currencyCode]['default'] >
                                $customData[$field][$currencyCode]['group_' . $groupId]) {
                                $original = $customData[$field][$currencyCode]['default_formated'];
                                $customData[$field][$currencyCode]['group_'.$groupId.'_original_formated'] = $original;
                            }
                        }
                    }
                } else {
                    if ($specialPrice[0] && $specialPrice[0] < $customData[$field][$currencyCode]['default']) {
                        $defaultOriginalFormated = $customData[$field][$currencyCode]['default_formated'];
                        $customData[$field][$currencyCode]['default_original_formated'] = $defaultOriginalFormated;

                        $defaultFormated = $this->priceCurrency->format(
                            $specialPrice[0],
                            false,
                            PriceCurrencyInterface::DEFAULT_PRECISION,
                            $store,
                            $currencyCode
                        );
                        $customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($specialPrice[0]);
                        $customData[$field][$currencyCode]['default_formated'] = $defaultFormated;
                    }
                }

                if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
                    $min = PHP_INT_MAX;
                    $max = 0;

                    $dashedFormat = '';

                    if ($type == 'bundle') {
                        /** @var \Magento\Bundle\Model\Product\Price $priceModel */
                        $priceModel = $product->getPriceModel();
                        list($min, $max) = $priceModel->getTotalPrices($product, null, $withTax, true);
                    }

                    if ($type == 'grouped' || $type == 'configurable') {
                        if (count($subProducts) > 0) {
                            /** @var Product $subProduct */
                            foreach ($subProducts as $subProduct) {
                                $price = (double) $this->catalogHelper->getTaxPrice(
                                    $product,
                                    $subProduct->getFinalPrice(),
                                    $withTax,
                                    null,
                                    null,
                                    null,
                                    $product->getStore(),
                                    null
                                );

                                $min = min($min, $price);
                                $max = max($max, $price);
                            }
                        } else {
                            $min = $max;
                        }
                    }

                    if ($min != $max) {
                        if ($currencyCode !== $baseCurrencyCode) {
                            $min = $this->priceCurrency->convert($min, $store, $currencyCode);
                        }

                        if ($currencyCode !== $baseCurrencyCode) {
                            $max = $this->priceCurrency->convert($max, $store, $currencyCode);
                        }

                        $dashedFormat =
                            $this->priceCurrency->format(
                                $min,
                                false,
                                PriceCurrencyInterface::DEFAULT_PRECISION,
                                $store,
                                $currencyCode
                            )
                            . ' - ' .
                            $this->priceCurrency->format(
                                $max,
                                false,
                                PriceCurrencyInterface::DEFAULT_PRECISION,
                                $store,
                                $currencyCode
                            );

                        if (isset($customData[$field][$currencyCode]['default_original_formated']) === false
                            || $min <= $customData[$field][$currencyCode]['default']) {
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
                                    $customData[$field][$currencyCode]['group_'.$groupId] = 0;
                                    $customData[$field][$currencyCode]['group_'.$groupId.'_formated'] = $dashedFormat;
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

                            $minFormated = $this->priceCurrency->format(
                                $min,
                                false,
                                PriceCurrencyInterface::DEFAULT_PRECISION,
                                $store,
                                $currencyCode
                            );
                            $customData[$field][$currencyCode]['default'] = $min;
                            $customData[$field][$currencyCode]['default_formated'] = $minFormated;
                        }
                    }

                    if ($areCustomersGroupsEnabled) {
                        foreach ($groups as $group) {
                            $groupId = (int) $group->getData('customer_group_id');

                            if ($customData[$field][$currencyCode]['group_' . $groupId] == 0) {
                                $customData[$field][$currencyCode]['group_' . $groupId] = $min;

                                if ($min === $max) {
                                    $default = $customData[$field][$currencyCode]['default_formated'];
                                    $customData[$field][$currencyCode]['group_'.$groupId.'_formated'] = $default;
                                } else {
                                    $customData[$field][$currencyCode]['group_'.$groupId.'_formated'] = $dashedFormat;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function getAllCategories($categoryIds)
    {
        $categories = $this->categoryHelper->getCoreCategories();

        $selectedCategories = [];
        foreach ($categoryIds as $id) {
            if (isset($categories[$id])) {
                $selectedCategories[] = $categories[$id];
            }
        }

        return $selectedCategories;
    }

    public function getObject(Product $product)
    {
        $type = $product->getTypeId();
        $storeId = $product->getStoreId();

        $this->logger->start('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId));
        $defaultData = [];

        $transport = new DataObject($defaultData);
        $this->eventManager->dispatch(
            'algolia_product_index_before',
            ['product' => $product, 'custom_data' => $transport]
        );

        $defaultData = $transport->getData();

        $visibility = $product->getVisibility();

        $visibleInCatalog = $this->visibility->getVisibleInCatalogIds();
        $visibleInSearch = $this->visibility->getVisibleInSearchIds();

        $urlParams = [
            '_secure' => $this->configHelper->useSecureUrlsInFrontend($product->getStoreId()),
            '_nosid' => true,
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
            $customData['description'] = $product->getData('description');
        }

        $categories = [];
        $categoriesWithPath = [];

        $_categoryIds = $product->getCategoryIds();

        if (is_array($_categoryIds) && count($_categoryIds) > 0) {
            $categoryCollection = $this->getAllCategories($_categoryIds);

            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($product->getStoreId());
            $rootCat = $store->getRootCategoryId();

            foreach ($categoryCollection as $category) {
                // Check and skip all categories that is not
                // in the path of the current store.
                $path = $category->getPath();
                $pathParts = explode('/', $path);
                if (isset($pathParts[1]) && $pathParts[1] != $rootCat) {
                    continue;
                }

                $categoryName = $category->getName();

                if ($categoryName) {
                    $categories[] = $categoryName;
                }

                $category->getUrlInstance()->setStore($product->getStoreId());
                $path = [];

                foreach ($category->getPathIds() as $treeCategoryId) {
                    if (!$this->configHelper->showCatsNotIncludedInNavigation($storeId)
                        && !$this->categoryHelper->isCategoryVisibleInMenu($treeCategoryId, $storeId)) {
                        // If the category should not be included in menu - skip it
                        $path[] = null;
                        continue;
                    }

                    $name = $this->categoryHelper->getCategoryName($treeCategoryId, $storeId);
                    if ($name) {
                        $path[] = $name;
                    }
                }

                $categoriesWithPath[] = $path;
            }
        }

        foreach ($categoriesWithPath as $result) {
            for ($i = count($result) - 1; $i > 0; $i--) {
                $categoriesWithPath[] = array_slice($result, 0, $i);
            }
        }

        $categoriesWithPath = array_intersect_key(
            $categoriesWithPath,
            array_unique(array_map('serialize', $categoriesWithPath))
        );

        $categoriesHierarchical = [];

        $levelName = 'level';

        foreach ($categoriesWithPath as $category) {
            for ($i = 0; $i < count($category); $i++) {
                if (isset($categoriesHierarchical[$levelName . $i]) === false) {
                    $categoriesHierarchical[$levelName . $i] = [];
                }

                if ($category[$i] === null) {
                    continue;
                }

                $categoriesHierarchical[$levelName . $i][] = implode(' /// ', array_slice($category, 0, $i + 1));
            }
        }

        foreach ($categoriesHierarchical as &$level) {
            $level = array_values(array_unique($level));
        }

        foreach ($categoriesWithPath as &$category) {
            $category = implode(' /// ', $category);
        }

        $customData['categories'] = $categoriesHierarchical;

        $customData['categories_without_path'] = $categories;

        $customData = $this->addImageData($customData, $product, $additionalAttributes);

        $subProducts = [];
        $ids = null;

        if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
            if ($type == 'bundle') {
                $ids = [];

                /** @var \Magento\Bundle\Model\Product\Type $typeInstance */
                $typeInstance = $product->getTypeInstance();

                $selection = $typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($product), $product);
                foreach ($selection as $option) {
                    $ids[] = $option->getProductId();
                }
            }

            if ($type == 'configurable' || $type == 'grouped') {
                $ids = $product->getTypeInstance()->getChildrenIds($product->getId());
                $ids = call_user_func_array('array_merge', $ids);
            }

            if (count($ids)) {
                $subProducts = $this->getProductCollectionQuery($product->getStoreId(), $ids, false)->load();
            }
        }

        if (false === isset($defaultData['in_stock'])) {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $customData['in_stock'] = $stockItem && (int) $stockItem->getIsInStock();
        }

        // skip default calculation if we have provided these attributes via the observer in $defaultData
        if (false === isset($defaultData['ordered_qty'])
            && $this->isAttributeEnabled($additionalAttributes, 'ordered_qty')) {
            $customData['ordered_qty'] = (int) $product->getData('ordered_qty');
        }

        if (false === isset($defaultData['total_ordered'])
            && $this->isAttributeEnabled($additionalAttributes, 'total_ordered')) {
            $customData['total_ordered'] = (int) $product->getData('total_ordered');
        }

        if (false === isset($defaultData['stock_qty'])
            && $this->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $customData['stock_qty'] = 0;

            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if ($stockItem) {
                $customData['stock_qty'] = (int) $stockItem->getQty();
            }
        }

        if ($this->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
            $customData['rating_summary'] = (int) $product->getData('rating_summary');
        }

        foreach ($additionalAttributes as $attribute) {
            if (isset($customData[$attribute['attribute']])) {
                continue;
            }

            $value = $product->getData($attribute['attribute']);

            /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
            $resource = $product->getResource();

            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attributeResource */
            $attributeResource = $resource->getAttribute($attribute['attribute']);
            if ($attributeResource) {
                $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());

                if ($value === null) {
                    /* Get values as array in children */
                    if ($type == 'configurable' || $type == 'grouped' || $type == 'bundle') {
                        $values = [];

                        $allProductsAreOutOfStock = true;

                        /** @var Product $subProduct */
                        foreach ($subProducts as $subProduct) {
                            $isInStock = (int) $this->stockRegistry->getStockItem($subProduct->getId())->getIsInStock();

                            if ($isInStock == false && $this->configHelper->indexOutOfStockOptions($storeId) == false) {
                                continue;
                            }

                            $allProductsAreOutOfStock = false;

                            $value = $subProduct->getData($attribute['attribute']);

                            if ($value) {
                                /** @var string|array $valueText */
                                $valueText = $subProduct->getAttributeText($attribute['attribute']);

                                if ($valueText) {
                                    if (is_array($valueText)) {
                                        foreach ($valueText as $valueText_elt) {
                                            $values[] = $valueText_elt;
                                        }
                                    } else {
                                        $values[] = $valueText;
                                    }
                                } else {
                                    $values[] = $attributeResource->getFrontend()->getValue($subProduct);
                                }
                            }
                        }

                        if (is_array($values) && count($values) > 0) {
                            $customData[$attribute['attribute']] = array_values(array_unique($values));
                        }

                        if (isset($customData['in_stock']) && $customData['in_stock'] && $allProductsAreOutOfStock) {
                            // Set main product out of stock if all
                            // sub-products is out of stock.
                            $customData['in_stock'] = 0;
                        }
                    }
                } else {
                    $valueText = null;

                    if (!is_array($value)) {
                        $valueText = $product->getAttributeText($attribute['attribute']);
                    }

                    if ($valueText) {
                        $value = $valueText;
                    } else {
                        $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());
                        $value = $attributeResource->getFrontend()->getValue($product);
                    }

                    if ($value) {
                        $customData[$attribute['attribute']] = $value;
                    }
                }
            }
        }

        $this->handlePrice($product, $subProducts, $customData);

        $customData['type_id'] = $type;

        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_subproducts_index',
            ['custom_data' => $transport, 'sub_products' => $subProducts, 'productObject' => $product]
        );
        $customData = $transport->getData();

        $customData = array_merge($customData, $defaultData);

        $this->algoliaHelper->castProductObject($customData);

        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_after_create_product_object',
            ['custom_data' => $transport, 'sub_products' => $subProducts, 'productObject' => $product]
        );
        $customData = $transport->getData();

        $this->logger->stop('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId));

        return $customData;
    }

    protected function addImageData(array $customData, Product $product, $additionalAttributes)
    {
        if (false === isset($customData['thumbnail_url'])) {
            $customData['thumbnail_url'] = $this->imageHelper
                ->init($product, 'thumbnail')
                ->resize(75, 75)
                ->getUrl();
        }

        if (false === isset($customData['image_url'])) {
            $this->imageHelper
                ->init($product, $this->configHelper->getImageType())
                ->resize($this->configHelper->getImageWidth(), $this->configHelper->getImageHeight());

            $customData['image_url'] = $this->imageHelper->getUrl();

            if ($this->isAttributeEnabled($additionalAttributes, 'media_gallery')) {
                $product->load($product->getId(), 'media_gallery');

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
