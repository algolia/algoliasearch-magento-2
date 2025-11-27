<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Model\Source\PaginationMode;
use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class InstantSearchHelperTest extends TestCase
{

    public const MAGENTO_GRID_PRODUCTS_NB = 9;
    public const MAGENTO_LIST_PRODUCTS_NB = 15;

    protected ?InstantSearchHelper $instantSearchHelper;

    protected ?ScopeConfigInterface $configInterface;
    protected ?WriterInterface $configWriter;
    protected ?Serializer $serializer;

    protected function setUp(): void
    {
        $this->configInterface = $this->createMock(ScopeConfigInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->serializer = $this->createMock(Serializer::class);

        $this->instantSearchHelper = new InstantSearchHelper(
            $this->configInterface,
            $this->configWriter,
            $this->serializer
        );
    }

    /**
     * @dataProvider conficProvider
     */
    public function testGetNumberOfProductResults($paginationMode, $customNbOfProducts, $expectedResult): void
    {
        $this->configInterface
            ->method('getValue')
            ->willReturnMap(
                [
                    [InstantSearchHelper::PAGINATION_MODE, ScopeInterface::SCOPE_STORE, null, $paginationMode],
                    [InstantSearchHelper::MAGENTO_GRID_PER_PAGE, ScopeInterface::SCOPE_STORE, null, self::MAGENTO_GRID_PRODUCTS_NB],
                    [InstantSearchHelper::MAGENTO_LIST_PER_PAGE, ScopeInterface::SCOPE_STORE, null, self::MAGENTO_LIST_PRODUCTS_NB],
                    [InstantSearchHelper::NUMBER_OF_PRODUCT_RESULTS, ScopeInterface::SCOPE_STORE, null, $customNbOfProducts],
                ]
            );

        // Sanity checks
        $this->assertEquals($paginationMode, $this->instantSearchHelper->getPaginationMode());
        $this->assertEquals(
            self::MAGENTO_GRID_PRODUCTS_NB,
            $this->instantSearchHelper->getMagentoGridProductsPerPage(ScopeInterface::SCOPE_STORE)
        );
        $this->assertEquals(
            self::MAGENTO_LIST_PRODUCTS_NB,
            $this->instantSearchHelper->getMagentoListProductsPerPage(ScopeInterface::SCOPE_STORE)
        );

        // Assert returned number of products according to the config
        $this->assertEquals($expectedResult, $this->instantSearchHelper->getNumberOfProductResults());
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
