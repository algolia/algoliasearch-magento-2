<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Model\Source\PaginationMode;
use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class InstantSearchHelperTest extends TestCase
{
    public const MAGENTO_GRID_PRODUCTS_NB = 9;
    public const MAGENTO_LIST_PRODUCTS_NB = 15;

    protected function createObjectToTest(
        ?ScopeConfigInterface $configInterface = null,
        ?WriterInterface $configWriter = null,
        ?Serializer $serializer = null,
    ): InstantSearchHelper {
        return new InstantSearchHelper(
            $configInterface ?? $this->createStub(ScopeConfigInterface::class),
            $configWriter ?? $this->createStub(WriterInterface::class),
            $serializer ?? $this->createStub(Serializer::class),
        );
    }

    #[DataProvider('conficProvider')]
    public function testGetNumberOfProductResults($paginationMode, $customNbOfProducts, $expectedResult): void
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('getValue')->willReturnMap(
            [
                [InstantSearchHelper::PAGINATION_MODE, ScopeInterface::SCOPE_STORE, null, $paginationMode],
                [InstantSearchHelper::MAGENTO_GRID_PER_PAGE, ScopeInterface::SCOPE_STORE, null, self::MAGENTO_GRID_PRODUCTS_NB],
                [InstantSearchHelper::MAGENTO_LIST_PER_PAGE, ScopeInterface::SCOPE_STORE, null, self::MAGENTO_LIST_PRODUCTS_NB],
                [InstantSearchHelper::NUMBER_OF_PRODUCT_RESULTS, ScopeInterface::SCOPE_STORE, null, $customNbOfProducts],
            ]
        );

        $instantSearchHelper = $this->createObjectToTest(configInterface: $configInterface);

        // Sanity checks
        $this->assertEquals($paginationMode, $instantSearchHelper->getPaginationMode());
        $this->assertEquals(
            self::MAGENTO_GRID_PRODUCTS_NB,
            $instantSearchHelper->getMagentoGridProductsPerPage(ScopeInterface::SCOPE_STORE)
        );
        $this->assertEquals(
            self::MAGENTO_LIST_PRODUCTS_NB,
            $instantSearchHelper->getMagentoListProductsPerPage(ScopeInterface::SCOPE_STORE)
        );

        // Assert returned number of products according to the config
        $this->assertEquals($expectedResult, $instantSearchHelper->getNumberOfProductResults());
    }

    public static function conficProvider(): array
    {
        return [
            [
                'paginationMode' => PaginationMode::PAGINATION_MAGENTO_GRID,
                'customNbOfProducts' => 18,
                'expectedResult' => self::MAGENTO_GRID_PRODUCTS_NB,
            ],
            [
                'paginationMode' => PaginationMode::PAGINATION_MAGENTO_LIST,
                'customNbOfProducts' => 18,
                'expectedResult' => self::MAGENTO_LIST_PRODUCTS_NB,
            ],
            [
                'paginationMode' => PaginationMode::PAGINATION_CUSTOM,
                'customNbOfProducts' => 18,
                'expectedResult' => 18,
            ],
            [
                'paginationMode' => PaginationMode::PAGINATION_CUSTOM,
                'customNbOfProducts' => 25,
                'expectedResult' => 25,
            ],
        ];
    }
}
