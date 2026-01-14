<?php

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
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
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
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    protected ?ConfigurationBlock $configurationBlock;

    protected ?ConfigHelper $config;
    protected ?AutocompleteHelper $autocompleteConfig;
    protected ?InstantSearchHelper $instantSearchConfig;
    protected ?PersonalizationHelper $personalizationHelper;
    protected ?CatalogSearchHelper $catalogSearchHelper;
    protected ?ProductHelper $productHelper;
    protected ?Currency $currency;
    protected ?Format $format;
    protected ?CurrentProduct $currentProduct;
    protected ?AlgoliaConnector $algoliaConnector;
    protected ?UrlHelper $urlHelper;
    protected ?FormKey $formKey;
    protected ?HttpContext $httpContext;
    protected ?CoreHelper $coreHelper;
    protected ?CategoryHelper $categoryHelper;
    protected ?SuggestionHelper $suggestionHelper;
    protected ?LandingPageHelper $landingPageHelper;
    protected ?CheckoutSession $checkoutSession;
    protected ?DateTime $date;
    protected ?CurrentCategory $currentCategory;
    protected ?SortingTransformer $sortingTransformer;
    protected ?Context $context;

    protected ?Http $request;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigHelper::class);
        $this->autocompleteConfig = $this->createMock(AutocompleteHelper::class);
        $this->instantSearchConfig = $this->createMock(InstantSearchHelper::class);
        $this->personalizationHelper = $this->createMock(PersonalizationHelper::class);
        $this->catalogSearchHelper = $this->createMock(CatalogSearchHelper::class);
        $this->productHelper = $this->createMock(ProductHelper::class);
        $this->currency = $this->createMock(Currency::class);
        $this->format = $this->createMock(Format::class);
        $this->currentProduct = $this->createMock(CurrentProduct::class);
        $this->algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $this->urlHelper = $this->createMock(UrlHelper::class);
        $this->formKey = $this->createMock(FormKey::class);
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->coreHelper = $this->createMock(CoreHelper::class);
        $this->categoryHelper = $this->createMock(CategoryHelper::class);
        $this->suggestionHelper = $this->createMock(SuggestionHelper::class);
        $this->landingPageHelper = $this->createMock(LandingPageHelper::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->date = $this->createMock(DateTime::class);
        $this->currentCategory = $this->createMock(CurrentCategory::class);
        $this->sortingTransformer = $this->createMock(SortingTransformer::class);
        $this->context = $this->createMock(Context::class);

        $this->request = $this->createMock(Http::class);
        $this->context->method('getRequest')->willReturn($this->request);

        $this->configurationBlock = new ConfigurationBlock(
            $this->config,
            $this->autocompleteConfig ,
            $this->instantSearchConfig ,
            $this->personalizationHelper ,
            $this->catalogSearchHelper ,
            $this->productHelper,
            $this->currency ,
            $this->format ,
            $this->currentProduct ,
            $this->algoliaConnector ,
            $this->urlHelper ,
            $this->formKey ,
            $this->httpContext ,
            $this->coreHelper ,
            $this->categoryHelper,
            $this->suggestionHelper ,
            $this->landingPageHelper ,
            $this->checkoutSession,
            $this->date ,
            $this->currentCategory ,
            $this->sortingTransformer ,
            $this->context,
        );
    }

    /**
     * @dataProvider searchPageDataProvider
     */
    public function testIsSearchPage($action, $categoryId, $categoryDisplayMode, $expectedResult): void
    {
        $this->config->method('isInstantEnabled')->willReturn(true);
        $this->request->method('getFullActionName')->willReturn($action);

        $controller = explode('_', $action);
        $controller = $controller[1];

        $this->request->method('getControllerName')->willReturn($controller);
        $this->config->method('replaceCategories')->willReturn(true);

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($categoryId);
        $category->method('getDisplayMode')->willReturn($categoryDisplayMode);
        $this->currentCategory->method('get')->willReturn($category);

        $this->assertEquals($expectedResult, $this->configurationBlock->isSearchPage());
    }

    public static function searchPageDataProvider(): array
    {
        return [
            [ // true if category has an ID
                'action' => 'catalog_category_view',
                'categoryId' => 1,
                'categoryDisplayMode' => 'PRODUCT',
                'expectedResult' => true
            ],
            [ // false if category has no ID
                'action' => 'catalog_category_view',
                'categoryId' => null,
                'categoryDisplayMode' => 'PRODUCT',
                'expectedResult' => false
            ],
            [ // false if category has a PAGE as display mode
                'action' => 'catalog_category_view',
                'categoryId' => 1,
                'categoryDisplayMode' => 'PAGE',
                'expectedResult' => false
            ],
            [ // true if catalogsearch
                'action' => 'catalogsearch_result_index',
                'categoryId' => null,
                'categoryDisplayMode' => 'FOO',
                'expectedResult' => true
            ],
            [ // true if landing page
                'action' => 'algolia_landingpage_view',
                'categoryId' => null,
                'categoryDisplayMode' => 'FOO',
                'expectedResult' => true
            ]
        ];
    }
}
