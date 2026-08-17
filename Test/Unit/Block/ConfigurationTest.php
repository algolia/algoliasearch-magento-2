<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block;

use Algolia\AlgoliaSearch\Block\Configuration as ConfigurationBlock;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\PersonalizationHelper;
use Algolia\AlgoliaSearch\Helper\Data as CoreHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Helper\LandingPageHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Magento\Catalog\Model\Category;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Locale\Format;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Helper\Data as CatalogSearchHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigurationTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $config = null,
        ?AutocompleteHelper $autocompleteConfig = null,
        ?InstantSearchHelper $instantSearchConfig = null,
        ?PersonalizationHelper $personalizationHelper = null,
        ?CatalogSearchHelper $catalogSearchHelper = null,
        ?ProductHelper $productHelper = null,
        ?Currency $currency = null,
        ?Format $format = null,
        ?CurrentProduct $currentProduct = null,
        ?AlgoliaConnector $algoliaConnector = null,
        ?UrlHelper $urlHelper = null,
        ?FormKey $formKey = null,
        ?HttpContext $httpContext = null,
        ?CoreHelper $coreHelper = null,
        ?CategoryHelper $categoryHelper = null,
        ?SuggestionHelper $suggestionHelper = null,
        ?LandingPageHelper $landingPageHelper = null,
        ?CheckoutSession $checkoutSession = null,
        ?DateTime $date = null,
        ?CurrentCategory $currentCategory = null,
        ?SortingTransformer $sortingTransformer = null,
        ?PriceKeyResolver $priceKeyResolver = null,
        ?CategoryPathProvider $categoryPathProvider = null,
        ?Http $request = null,
    ): ConfigurationBlock {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request ?? $this->createStub(Http::class));

        return new ConfigurationBlock(
            $config ?? $this->createStub(ConfigHelper::class),
            $autocompleteConfig ?? $this->createStub(AutocompleteHelper::class),
            $instantSearchConfig ?? $this->createStub(InstantSearchHelper::class),
            $personalizationHelper ?? $this->createStub(PersonalizationHelper::class),
            $catalogSearchHelper ?? $this->createStub(CatalogSearchHelper::class),
            $productHelper ?? $this->createStub(ProductHelper::class),
            $currency ?? $this->createStub(Currency::class),
            $format ?? $this->createStub(Format::class),
            $currentProduct ?? $this->createStub(CurrentProduct::class),
            $algoliaConnector ?? $this->createStub(AlgoliaConnector::class),
            $urlHelper ?? $this->createStub(UrlHelper::class),
            $formKey ?? $this->createStub(FormKey::class),
            $httpContext ?? $this->createStub(HttpContext::class),
            $coreHelper ?? $this->createStub(CoreHelper::class),
            $categoryHelper ?? $this->createStub(CategoryHelper::class),
            $suggestionHelper ?? $this->createStub(SuggestionHelper::class),
            $landingPageHelper ?? $this->createStub(LandingPageHelper::class),
            $checkoutSession ?? $this->createStub(CheckoutSession::class),
            $date ?? $this->createStub(DateTime::class),
            $currentCategory ?? $this->createStub(CurrentCategory::class),
            $sortingTransformer ?? $this->createStub(SortingTransformer::class),
            $priceKeyResolver ?? $this->createStub(PriceKeyResolver::class),
            $categoryPathProvider ?? $this->createStub(CategoryPathProvider::class),
            $context,
        );
    }

    #[DataProvider('searchPageDataProvider')]
    public function testIsSearchPage($action, $categoryId, $categoryDisplayMode, $expectedResult): void
    {
        $instantSearchConfig = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfig->method('isEnabled')->willReturn(true);
        $instantSearchConfig->method('shouldReplaceCategories')->willReturn(true);

        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($action);

        $controller = explode('_', $action);
        $controller = $controller[1];
        $request->method('getControllerName')->willReturn($controller);

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn($categoryId);
        $category->method('getDisplayMode')->willReturn($categoryDisplayMode);

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $configurationBlock = $this->createObjectToTest(
            instantSearchConfig: $instantSearchConfig,
            currentCategory: $currentCategory,
            request: $request,
        );

        $this->assertEquals($expectedResult, $configurationBlock->isSearchPage());
    }

    public function testAreCategoriesInFacetsReturnsTrueWhenCategoriesAttributePresent(): void
    {
        $facets = [
            ['attribute' => 'color'],
            ['attribute' => 'categories'],
        ];

        $configurationBlock = $this->createObjectToTest();

        $this->assertTrue($this->invokeMethod($configurationBlock, 'areCategoriesInFacets', [$facets]));
    }

    public function testAreCategoriesInFacetsReturnsFalseWhenCategoriesAttributeAbsent(): void
    {
        $facets = [
            ['attribute' => 'color'],
            ['attribute' => 'size'],
        ];

        $configurationBlock = $this->createObjectToTest();

        $this->assertFalse($this->invokeMethod($configurationBlock, 'areCategoriesInFacets', [$facets]));
    }

    public function testAreCategoriesInFacetsReturnsFalseWhenFacetsIsEmpty(): void
    {
        $configurationBlock = $this->createObjectToTest();

        $this->assertFalse($this->invokeMethod($configurationBlock, 'areCategoriesInFacets', [[]]));
    }

    public function testGetUrlTrackedParametersIncludesPageWhenInfiniteScrollDisabled(): void
    {
        $instantSearchConfig = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfig->method('isInfiniteScrollEnabled')->willReturn(false);

        $configurationBlock = $this->createObjectToTest(instantSearchConfig: $instantSearchConfig);

        $params = $this->invokeMethod($configurationBlock, 'getUrlTrackedParameters');

        $this->assertContains('page', $params);
    }

    public function testGetUrlTrackedParametersExcludesPageWhenInfiniteScrollEnabled(): void
    {
        $instantSearchConfig = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfig->method('isInfiniteScrollEnabled')->willReturn(true);

        $configurationBlock = $this->createObjectToTest(instantSearchConfig: $instantSearchConfig);

        $params = $this->invokeMethod($configurationBlock, 'getUrlTrackedParameters');

        $this->assertNotContains('page', $params);
    }

    public function testGetUrlTrackedParametersAlwaysIncludesBaseParams(): void
    {
        $instantSearchConfig = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfig->method('isInfiniteScrollEnabled')->willReturn(true);

        $configurationBlock = $this->createObjectToTest(instantSearchConfig: $instantSearchConfig);

        $params = $this->invokeMethod($configurationBlock, 'getUrlTrackedParameters');

        $this->assertContains('query', $params);
        $this->assertContains('attribute:*', $params);
        $this->assertContains('index', $params);
    }

    public static function searchPageDataProvider(): array
    {
        return [
            [ // true if category has an ID
                'action' => 'catalog_category_view',
                'categoryId' => 1,
                'categoryDisplayMode' => 'PRODUCT',
                'expectedResult' => true,
            ],
            [ // false if category has no ID
                'action' => 'catalog_category_view',
                'categoryId' => null,
                'categoryDisplayMode' => 'PRODUCT',
                'expectedResult' => false,
            ],
            [ // false if category has a PAGE as display mode
                'action' => 'catalog_category_view',
                'categoryId' => 1,
                'categoryDisplayMode' => 'PAGE',
                'expectedResult' => false,
            ],
            [ // true if catalogsearch
                'action' => 'catalogsearch_result_index',
                'categoryId' => null,
                'categoryDisplayMode' => 'FOO',
                'expectedResult' => true,
            ],
            [ // true if landing page
                'action' => 'algolia_landingpage_view',
                'categoryId' => null,
                'categoryDisplayMode' => 'FOO',
                'expectedResult' => true,
            ],
        ];
    }
}
