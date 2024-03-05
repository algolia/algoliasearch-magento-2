<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use DateTime;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\Customer\Model\Group;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Catalog\Api\ScopedProductTierPriceManagementInterface;

abstract class ProductWithoutChildren
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var CollectionFactory
     */
    protected $customerGroupCollectionFactory;
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;
    /**
     * @var CatalogHelper
     */
    protected $catalogHelper;
    /**
     * @var TaxHelper
     */
    protected $taxHelper;
    /**
     * @var Rule
     */
    protected $rule;
    /**
     * @var ProductFactory
     */
    protected $productloader;

    /**
     * @var GroupExcludedWebsiteRepositoryInterface
     */
    protected $groupExcludedWebsiteRepository;

    /**
     * @var ScopedProductTierPriceManagementInterface
     */
    private $productTierPrice;

    /**
     * @var Logger
     */
    protected $logger;

    protected $store;
    protected $baseCurrencyCode;
    protected $groups;
    protected $areCustomersGroupsEnabled;
    protected $customData = [];

    /**
     * @param ConfigHelper $configHelper
     * @param CollectionFactory $customerGroupCollectionFactory
     * @param GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param CatalogHelper $catalogHelper
     * @param TaxHelper $taxHelper
     * @param Rule $rule
     * @param ProductFactory $productloader
     * @param ScopedProductTierPriceManagementInterface $productTierPrice
     * @param Logger $logger
     */
    public function __construct(
        ConfigHelper $configHelper,
        CollectionFactory $customerGroupCollectionFactory,
        GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
        PriceCurrencyInterface $priceCurrency,
        CatalogHelper $catalogHelper,
        TaxHelper $taxHelper,
        Rule $rule,
        ProductFactory $productloader,
        ScopedProductTierPriceManagementInterface $productTierPrice,
        Logger $logger
    ) {
        $this->configHelper = $configHelper;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->groupExcludedWebsiteRepository = $groupExcludedWebsiteRepository;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;
        $this->taxHelper = $taxHelper;
        $this->rule = $rule;
        $this->productloader = $productloader;
        $this->productTierPrice = $productTierPrice;
        $this->logger = $logger;
    }

    /**
     * @param $customData
     * @param Product $product
     * @param $subProducts
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addPriceData($customData, Product $product, $subProducts): array
    {
        $this->customData = $customData;
        $this->store = $product->getStore();
        $this->areCustomersGroupsEnabled = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId());
        $currencies = $this->store->getAvailableCurrencyCodes(true);
        $this->baseCurrencyCode = $this->store->getBaseCurrencyCode();
        $this->groups = $this->customerGroupCollectionFactory->create();
        $fields = $this->getFields();
        if (!$this->areCustomersGroupsEnabled) {
            $this->groups->addFieldToFilter('main_table.customer_group_id', 0);
        } else {
            $excludedGroups = array();
            foreach ($this->groups as $group) {
                $groupId = (int)$group->getData('customer_group_id');
                $excludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId);
                if (in_array($product->getStore()->getWebsiteId(), $excludedWebsites)) {
                    $excludedGroups[] = $groupId;
                }
            }
            if(count($excludedGroups) > 0) {
                $this->groups->addFieldToFilter('main_table.customer_group_id', ["nin" => $excludedGroups]);
            }
        }
        // price/price_with_tax => true/false
        foreach ($fields as $field => $withTax) {
            $this->customData[$field] = [];
            $product->setPriceCalculation(true);
            foreach ($currencies as $currencyCode) {
                $this->customData[$field][$currencyCode] = [];
                $price = $product->getPrice();
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $price = $this->convertPrice($price, $currencyCode);
                }
                $price = $this->getTaxPrice($product, $price, $withTax);
                $this->customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($price);
                $this->customData[$field][$currencyCode]['default_formated'] = $this->formatPrice($price, $currencyCode);
                $specialPrice = $this->getSpecialPrice($product, $currencyCode, $withTax, $subProducts);
                $tierPrice = $this->getTierPrice($product, $currencyCode, $withTax);
                if ($this->areCustomersGroupsEnabled) {
                    $this->addCustomerGroupsPrices($product, $currencyCode, $withTax, $field);
                }
                $this->customData[$field][$currencyCode]['special_from_date'] =
                    (!empty($product->getSpecialFromDate())) ? strtotime($product->getSpecialFromDate()) : '';
                $this->customData[$field][$currencyCode]['special_to_date'] =
                    (!empty($product->getSpecialToDate())) ? strtotime($product->getSpecialToDate()) : '';
                $this->addSpecialPrices($specialPrice, $field, $currencyCode);
                $this->addTierPrices($tierPrice, $field, $currencyCode);
                $this->addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field);
            }
        }

        return $this->customData;
    }

    /**
     * @return array
     */
    protected function getFields(): array
    {
        $priceDisplayType = $this->taxHelper->getPriceDisplayType($this->store);
        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX) {
            return ['price' => false];
        }
        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX) {
            return ['price' => true];
        }
        return ['price' => false, 'price_with_tax' => true];
    }

    /**
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @param $field
     * @return void
     */
    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field)
    {
        // Empty for products without children
    }

    /**
     * @param $amount
     * @param $currencyCode
     * @return mixed
     */
    protected function formatPrice($amount, $currencyCode)
    {
        $currency = $this->priceCurrency->getCurrency($this->store, $currencyCode);
        $options = ['locale' => $this->configHelper->getStoreLocale($this->store->getId())];
        return $currency->formatPrecision($amount, PriceCurrencyInterface::DEFAULT_PRECISION, $options, false);
    }

    /**
     * @param $amount
     * @param $currencyCode
     * @return float
     */
    protected function convertPrice($amount, $currencyCode): float
    {
        return $this->priceCurrency->convert($amount, $this->store, $currencyCode);
    }

    /**
     * @param $product
     * @param $amount
     * @param $withTax
     * @return float
     */
    public function getTaxPrice($product, $amount, $withTax): float
    {
        return (float) $this->catalogHelper->getTaxPrice(
            $product,
            $amount,
            $withTax,
            null,
            null,
            null,
            $this->store,
            null
        );
    }

    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @param $subProducts
     * @return array
     */
    protected function getSpecialPrice(Product $product, $currencyCode, $withTax, $subProducts): array
    {
        $specialPrice = [];
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $specialPrices[$groupId] = [];
            $specialPrices[$groupId][] = $this->getRulePrice($groupId, $product, $subProducts);
            // The price with applied catalog rules
            $specialPrices[$groupId][] = $product->getFinalPrice(); // The product's special price
            $specialPrices[$groupId] = array_filter($specialPrices[$groupId], function ($price) {
                return $price > 0;
            });
            $specialPrice[$groupId] = false;
            if ($specialPrices[$groupId] && $specialPrices[$groupId] !== []) {
                $specialPrice[$groupId] = min($specialPrices[$groupId]);
            }
            if ($specialPrice[$groupId]) {
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $specialPrice[$groupId] =
                        $this->priceCurrency->round($this->convertPrice($specialPrice[$groupId], $currencyCode));
                }
                $specialPrice[$groupId] = $this->getTaxPrice($product, $specialPrice[$groupId], $withTax);
            }
        }
        return $specialPrice;
    }

    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
    protected function getTierPrice(Product $product, $currencyCode, $withTax)
    {
        $tierPrice = [];
        $tierPrices = [];

        if (!empty($product->getTierPrices())) {
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $productTierPrices = $product->getTierPrices();
            foreach ($productTierPrices as $productTierPrice) {
                if (!isset($tierPrices[$productTierPrice->getCustomerGroupId()])) {
                    $tierPrices[$productTierPrice->getCustomerGroupId()] = $productTierPrice->getValue();

                    continue;
                }

                $tierPrices[$productTierPrice->getCustomerGroupId()] = min(
                    $tierPrices[$productTierPrice->getCustomerGroupId()],
                    $productTierPrice->getValue()
                );
            }
        } else {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $customerGroupId = (int) $group->getData('customer_group_id');
                $productTierPrices = $this->productTierPrice->getList($product->getSku(), $customerGroupId);
                if(!empty($productTierPrices)) {
                    foreach ($productTierPrices as $productTierPrice) {
                        if (!isset($tierPrices[$productTierPrice->getCustomerGroupId()])) {
                            $tierPrices[$productTierPrice->getCustomerGroupId()] = $productTierPrice->getValue();
                            continue;
                        }
                        $tierPrices[$productTierPrice->getCustomerGroupId()] = min(
                            $tierPrices[$productTierPrice->getCustomerGroupId()],
                            $productTierPrice->getValue()
                        );
                    }
                }
            }
        }

        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $tierPrice[$groupId] = false;

            $currentTierPrice = null;
            if (!isset($tierPrices[$groupId]) && !isset($tierPrices[GroupInterface::CUST_GROUP_ALL])) {
                continue;
            }

            if (isset($tierPrices[GroupInterface::CUST_GROUP_ALL])
                && $tierPrices[GroupInterface::CUST_GROUP_ALL] !== []) {
                $currentTierPrice = $tierPrices[GroupInterface::CUST_GROUP_ALL];
            }

            if (isset($tierPrices[$groupId]) && $tierPrices[$groupId] !== []) {
                $currentTierPrice = $currentTierPrice === null ?
                    $tierPrices[$groupId] :
                    min($currentTierPrice, $tierPrices[$groupId]);
            }

            if ($currencyCode !== $this->baseCurrencyCode) {
                $currentTierPrice =
                    $this->priceCurrency->round($this->convertPrice($currentTierPrice, $currencyCode));
            }
            $tierPrice[$groupId] = $this->getTaxPrice($product, $currentTierPrice, $withTax);
        }

        return $tierPrice;
    }

    /**
     * @param $tierPrice
     * @param $field
     * @param $currencyCode
     * @return void
     */
    protected function addTierPrices($tierPrice, $field, $currencyCode)
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($tierPrice[$groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_tier'] = $tierPrice[$groupId];

                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_tier_formated'] =
                        $this->formatPrice($tierPrice[$groupId], $currencyCode);
                }
            }

            return;
        }

        if ($tierPrice[0]) {
            $this->customData[$field][$currencyCode]['default_tier'] = $this->priceCurrency->round($tierPrice[0]);
            $this->customData[$field][$currencyCode]['default_tier_formated'] =
                $this->formatPrice($tierPrice[0], $currencyCode);
        }
    }
    # TODO bookmarking getRulePrice function for a future refactor effort.
    /**
     * @param $groupId
     * @param $product
     * @param $subProducts
     * @return float
     */
    protected function getRulePrice($groupId, $product, $subProducts)
    {
        return (float) $this->rule->getRulePrice(
            new DateTime(),
            $this->store->getWebsiteId(),
            $groupId,
            $product->getId()
        );
    }

    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @param $field
     * @return void
     */
    protected function addCustomerGroupsPrices(Product $product, $currencyCode, $withTax, $field)
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->convertPrice($discountedPrice, $currencyCode);
            }
            if ($discountedPrice !== false) {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->getTaxPrice($product, $discountedPrice, $withTax);
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->formatPrice(
                        $this->customData[$field][$currencyCode]['group_' . $groupId],
                        $currencyCode
                    );
                if ($this->customData[$field][$currencyCode]['default'] >
                    $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                        $this->customData[$field][$currencyCode]['default_formated'];
                }
            } else {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->customData[$field][$currencyCode]['default'];
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->customData[$field][$currencyCode]['default_formated'];
            }
        }

        $product->setData('customer_group_id', null);
    }

    /**
     * @param $specialPrice
     * @param $field
     * @param $currencyCode
     * @return void
     */
    protected function addSpecialPrices($specialPrice, $field, $currencyCode): void
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($specialPrice[$groupId]
                    && $specialPrice[$groupId] < $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId] = $specialPrice[$groupId];
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                        $this->formatPrice($specialPrice[$groupId], $currencyCode);
                    if ($this->customData[$field][$currencyCode]['default'] >
                        $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                        $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                            $this->customData[$field][$currencyCode]['default_formated'];
                    }
                }
            }
            return;
        }

        if ($specialPrice[0] && $specialPrice[0] < $this->customData[$field][$currencyCode]['default']) {
            $this->customData[$field][$currencyCode]['default_original_formated'] =
                $this->customData[$field][$currencyCode]['default_formated'];
            $this->customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($specialPrice[0]);
            $this->customData[$field][$currencyCode]['default_formated'] =
                $this->formatPrice($specialPrice[0], $currencyCode);
        }
    }
}
