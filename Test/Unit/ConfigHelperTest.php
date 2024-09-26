<?php

namespace Algolia\AlgoliaSearch\Test\Unit;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ConfigHelperTestable extends ConfigHelper
{
    /** expose protected methods for unit testing */
    public function serialize(array $value): string
    {
        return parent::serialize($value);
    }
}

class ConfigHelperTest extends TestCase
{
    protected ConfigHelperTestable $configHelper;
    protected ScopeConfigInterface $configInterface;
    protected WriterInterface $configWriter;
    protected StoreManagerInterface $storeManager;
    protected Currency $currency;
    protected DirCurrency $dirCurrency;
    protected DirectoryList $directoryList;
    protected ResourceInterface $moduleResource;
    protected ProductMetadataInterface $productMetadata;
    protected ManagerInterface $eventManager;
    protected SerializerInterface $serializer;
    protected GroupCollection $groupCollection;
    protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository;
    protected CookieHelper $cookieHelper;

    public function setUp(): void
    {
        $this->configInterface = $this->createMock(ScopeConfigInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->currency = $this->createMock(Currency::class);
        $this->dirCurrency = $this->createMock(DirCurrency::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->moduleResource = $this->createMock(ResourceInterface::class);
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->groupCollection = $this->createMock(GroupCollection::class);
        $this->groupExcludedWebsiteRepository = $this->createMock(GroupExcludedWebsiteRepositoryInterface::class);
        $this->cookieHelper = $this->createMock(CookieHelper::class);

        $this->configHelper = new ConfigHelperTestable(
            $this->configInterface,
            $this->configWriter,
            $this->storeManager,
            $this->currency,
            $this->dirCurrency,
            $this->directoryList,
            $this->moduleResource,
            $this->productMetadata,
            $this->eventManager,
            $this->serializer,
            $this->groupCollection,
            $this->groupExcludedWebsiteRepository,
            $this->cookieHelper
        );
    }

    public function testGetIndexPrefix()
    {
        $testPrefix = 'foo_bar_';
        $this->configInterface->method('getValue')->willReturn($testPrefix);
        $this->assertEquals($testPrefix, $this->configHelper->getIndexPrefix());
    }

    public function testGetIndexPrefixWhenNull() {
        $this->configInterface->method('getValue')->willReturn(null);
        $this->assertEquals('', $this->configHelper->getIndexPrefix());
    }

    public function testSerializerReturnsString() {
        $this->serializer->method('serialize')->willReturn('{"foo":"bar"}');
        $array = [
            'foo' => 'bar'
        ];
        $result = $this->configHelper->serialize($array);
        $this->assertEquals('{"foo":"bar"}', $result);
    }

    public function testSerializerFailure() {
        $this->serializer->method('serialize')->willReturn(false);
        $array = [
            'foo' => 'bar'
        ];
        $result = $this->configHelper->serialize($array);
        $this->assertEquals('', $result);
    }
}
