<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Service\Serializer;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as DirCurrency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Weee\Helper\Data as WeeeHelper;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigHelperTest extends TestCase
{
    protected function createObjectToTest(
        ?ScopeConfigInterface $configInterface = null,
        ?WriterInterface $configWriter = null,
        ?StoreManagerInterface $storeManager = null,
        ?Currency $currency = null,
        ?DirCurrency $dirCurrency = null,
        ?DirectoryList $directoryList = null,
        ?ResourceInterface $moduleResource = null,
        ?ProductMetadataInterface $productMetadata = null,
        ?ManagerInterface $eventManager = null,
        ?Serializer $serializer = null,
        ?GroupCollection $groupCollection = null,
        ?GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository = null,
        ?CookieHelper $cookieHelper = null,
        ?AutocompleteHelper $autocompleteHelper = null,
        ?InstantSearchHelper $instantSearchHelper = null,
        ?QueueHelper $queueHelper = null,
        ?WeeeHelper $weeeHelper = null,
    ): ConfigHelper {
        return new ConfigHelper(
            $configInterface ?? $this->createStub(ScopeConfigInterface::class),
            $configWriter ?? $this->createStub(WriterInterface::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $currency ?? $this->createStub(Currency::class),
            $dirCurrency ?? $this->createStub(DirCurrency::class),
            $directoryList ?? $this->createStub(DirectoryList::class),
            $moduleResource ?? $this->createStub(ResourceInterface::class),
            $productMetadata ?? $this->createStub(ProductMetadataInterface::class),
            $eventManager ?? $this->createStub(ManagerInterface::class),
            $serializer ?? $this->createStub(Serializer::class),
            $groupCollection ?? $this->createStub(GroupCollection::class),
            $groupExcludedWebsiteRepository ?? $this->createStub(GroupExcludedWebsiteRepositoryInterface::class),
            $cookieHelper ?? $this->createStub(CookieHelper::class),
            $autocompleteHelper ?? $this->createStub(AutocompleteHelper::class),
            $instantSearchHelper ?? $this->createStub(InstantSearchHelper::class),
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $weeeHelper ?? $this->createStub(WeeeHelper::class),
        );
    }

    public function testGetIndexPrefix()
    {
        $testPrefix = 'foo_bar_';

        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('getValue')->willReturn($testPrefix);

        $configHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertEquals($testPrefix, $configHelper->getIndexPrefix());
    }

    public function testGetIndexPrefixWhenNull()
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('getValue')->willReturn(null);

        $configHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertEquals('', $configHelper->getIndexPrefix());
    }

    #[DataProvider('isEnabledFrontEndProvider')]
    public function testIsEnabledFrontEnd(
        bool $isAutocompleteEnabled,
        bool $isInstantSearchEnabled,
        bool $expectedResult
    ): void {
        $storeId = 1;

        $autocompleteHelper = $this->createStub(AutocompleteHelper::class);
        $autocompleteHelper->method('isEnabled')->willReturn($isAutocompleteEnabled);

        $instantSearchHelper = $this->createStub(InstantSearchHelper::class);
        $instantSearchHelper->method('isEnabled')->willReturn($isInstantSearchEnabled);

        $configHelper = $this->createObjectToTest(
            autocompleteHelper: $autocompleteHelper,
            instantSearchHelper: $instantSearchHelper,
        );

        $this->assertSame($expectedResult, $configHelper->isEnabledFrontEnd($storeId));
    }

    public static function isEnabledFrontEndProvider(): array
    {
        return [
            'Both enabled' => [
                'isAutocompleteEnabled' => true,
                'isInstantSearchEnabled' => true,
                'expectedResult' => true,
            ],
            'Only autocomplete enabled' => [
                'isAutocompleteEnabled' => true,
                'isInstantSearchEnabled' => false,
                'expectedResult' => true,
            ],
            'Only instant search enabled' => [
                'isAutocompleteEnabled' => false,
                'isInstantSearchEnabled' => true,
                'expectedResult' => true,
            ],
            'Neither enabled' => [
                'isAutocompleteEnabled' => false,
                'isInstantSearchEnabled' => false,
                'expectedResult' => false,
            ],
        ];
    }
}
