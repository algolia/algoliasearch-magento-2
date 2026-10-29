<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager as PriceManagerV1;
use Algolia\AlgoliaSearch\Model\Source\Pricing;
use Algolia\AlgoliaSearch\Service\Product\PricingManagerV2;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Abstract base test case for Pricing V1 vs V2 integration tests
 *
 * Provides shared utilities for:
 * - Config management (forcing V1 to use its actual implementation)
 * - Price comparison logic with float tolerance
 * - Product loading and sub-product handling
 * - Recursive array comparison
 *
 * Expected differences (excluded from comparison):
 * - Keys ending with "_max" (V1-only fields)
 * - Key "default_original" (architectural difference)
 *
 * Child classes implement specific test scenarios (default, customer groups, catalog rules, etc.)
 */
abstract class AbstractPricingV1VsV2TestCase extends TestCase
{
    protected const FLOAT_TOLERANCE = 0.001;
    protected const CONFIG_PATH_PRICING_ENGINE = 'algoliasearch_products/products/pricing_engine';

    protected ?PriceManagerV1 $v1 = null;
    protected ?PricingManagerV2 $v2 = null;
    protected ?ProductRepositoryInterface $productRepository = null;
    protected ?MutableScopeConfigInterface $mutableConfig = null;
    protected ?ScopeConfigInterface $scopeConfig = null;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->mutableConfig = $objectManager->get(MutableScopeConfigInterface::class);
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);

        // Force V1 to use its actual implementation (not delegate to V2)
        $this->mutableConfig->setValue(
            self::CONFIG_PATH_PRICING_ENGINE,
            Pricing::PRICING_V1,
            ScopeInterface::SCOPE_STORE
        );

        // Get instances AFTER setting config to ensure V1 doesn't delegate to V2
        $this->v1 = $objectManager->get(PriceManagerV1::class);
        $this->v2 = $objectManager->get(PricingManagerV2::class);
    }

    /**
     * Assert V1 and V2 produce identical results
     */
    protected function assertPricingMatches(string $sku): void
    {
        $product = $this->loadProductOrSkip($sku);
        $subProducts = $this->getSubProducts($product);

        // Calculate with V1
        $v1Result = $this->v1->addPriceDataByProductType([], $product, $subProducts);

        // Calculate with V2
        $v2Result = $this->v2->addPriceDataByProductType([], $product, $subProducts);

        // Compare results
        $this->assertPricingDataEquals($v1Result, $v2Result, $product);
    }

    /**
     * Assert pricing data arrays are equal
     */
    protected function assertPricingDataEquals(array $v1Data, array $v2Data, Product $product): void
    {
        $errors = [];

        // Check same price fields exist
        $v1Keys = array_keys($v1Data);
        $v2Keys = array_keys($v2Data);

        if ($v1Keys !== $v2Keys) {
            $errors[] = sprintf(
                "Price fields mismatch - V1: [%s], V2: [%s]",
                implode(', ', $v1Keys),
                implode(', ', $v2Keys)
            );
        }

        // Compare each price field
        foreach ($v1Data as $field => $v1Currencies) {
            if (!isset($v2Data[$field])) {
                $errors[] = "Missing field in V2: {$field}";
                continue;
            }

            $v2Currencies = $v2Data[$field];

            // Check same currencies
            $v1CurrencyCodes = array_keys($v1Currencies);
            $v2CurrencyCodes = array_keys($v2Currencies);

            if ($v1CurrencyCodes !== $v2CurrencyCodes) {
                $errors[] = sprintf(
                    "Currency mismatch in field '{$field}' - V1: [%s], V2: [%s]",
                    implode(', ', $v1CurrencyCodes),
                    implode(', ', $v2CurrencyCodes)
                );
                continue;
            }

            // Compare price data for each currency
            foreach ($v1Currencies as $currency => $v1PriceData) {
                $v2PriceData = $v2Currencies[$currency];

                $this->comparePriceData($v1PriceData, $v2PriceData, "{$field}[{$currency}]", $errors);
            }
        }

        if (!empty($errors)) {
            $this->fail(sprintf(
                "Pricing mismatch for product %s (%s):\n%s",
                $product->getSku(),
                $product->getTypeId(),
                implode("\n", $errors)
            ));
        }
    }

    /**
     * Compare individual price data arrays recursively
     */
    protected function comparePriceData(array $v1, array $v2, string $context, array &$errors): void
    {
        $allKeys = array_unique(array_merge(array_keys($v1), array_keys($v2)));

        foreach ($allKeys as $key) {
            // Skip keys ending with "_max" - these are V1-only fields (expected difference)
            if (str_ends_with((string) $key, '_max')) {
                continue;
            }

            // Skip 'default_original' - architectural difference between V1 and V2
            if ($key === 'default_original' || str_ends_with((string) $key, '_min_original')) {
                continue;
            }

            $v1Value = $v1[$key] ?? null;
            $v2Value = $v2[$key] ?? null;

            // Handle numeric comparisons with tolerance
            if (is_numeric($v1Value) && is_numeric($v2Value)) {
                // Round to 2 decimals (cents) before comparison to handle float precision differences
                // Bundle products use different calculation methods (native PriceInfo vs manual sum)
                $v1Rounded = round((float) $v1Value, 2);
                $v2Rounded = round((float) $v2Value, 2);
                $diff = abs($v1Rounded - $v2Rounded);

                if ($diff > self::FLOAT_TOLERANCE) {
                    $errors[] = sprintf(
                        "  %s.%s: V1=%.4f, V2=%.4f (diff: %.4f)",
                        $context,
                        $key,
                        $v1Value,
                        $v2Value,
                        $diff
                    );
                }
            }
            // Handle array comparisons (e.g., group prices)
            elseif (is_array($v1Value) && is_array($v2Value)) {
                $this->comparePriceData($v1Value, $v2Value, "{$context}.{$key}", $errors);
            }
            // Handle other value comparisons
            elseif ($v1Value !== $v2Value) {
                // Allow false/null/empty equivalence
                if (!(empty($v1Value) && empty($v2Value))) {
                    $errors[] = sprintf(
                        "  %s.%s: V1=%s, V2=%s",
                        $context,
                        $key,
                        var_export($v1Value, true),
                        var_export($v2Value, true)
                    );
                }
            }
        }
    }

    /**
     * Get sub-products for complex product types
     */
    protected function getSubProducts(Product $product): array
    {
        return match ($product->getTypeId()) {
            'configurable' => $product->getTypeInstance()->getUsedProducts($product) ?? [],
            'bundle' => $this->getBundleSelections($product),
            'grouped' => $product->getTypeInstance()->getAssociatedProducts($product) ?? [],
            default => []
        };
    }

    /**
     * Get bundle product selections
     */
    protected function getBundleSelections(Product $product): array
    {
        $optionCollection = $product->getTypeInstance()->getOptionsCollection($product);
        $selectionCollection = $product->getTypeInstance()->getSelectionsCollection(
            $product->getTypeInstance()->getOptionsIds($product),
            $product
        );

        $selections = [];
        foreach ($selectionCollection as $selection) {
            $selections[] = $selection;
        }

        return $selections;
    }

    /**
     * Load product or skip test if not found
     */
    protected function loadProductOrSkip(string $sku): Product
    {
        try {
            return $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            $this->markTestSkipped(sprintf(
                'Product "%s" not found. Install Magento sample data: bin/magento sampledata:deploy',
                $sku
            ));
        }
    }
}
