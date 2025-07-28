<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Setup\Patch\Schema\ConfigPatch;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento246CE;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento246EE;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento247CE;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento247EE;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /** @var bool */
    private $boostrapped = false;

    /** @var string */
    protected $indexPrefix;

    /** @var ConfigHelper */
    protected $configHelper;

    /** @var Magento246CE|Magento246EE|Magento247CE|Magento247EE */
    protected $assertValues;

    /** @var ProductMetadataInterface */
    protected $productMetadata;

    protected ?string $indexSuffix = null;

    protected ?IndexOptionsBuilder $indexOptionsBuilder = null;
    protected ?AlgoliaConnector $algoliaConnector = null;
    protected ?IndexNameFetcher $indexNameFetcher = null;

    protected function setUp(): void
    {
        $this->bootstrap();
    }

    /**
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     */
    protected function tearDown(): void
    {
        $this->clearIndices();
        $this->algoliaConnector->waitLastTask();
        $this->clearIndices(); // Remaining replicas
    }

    protected function getIndexName(string $storeIndexPart): string
    {
        return $this->indexPrefix . $storeIndexPart . ($this->indexSuffix ? '_' . $this->indexSuffix : '');
    }

    protected function resetConfigs($configs = [])
    {
        /** @var ConfigPatch $installClass */
        $installClass = $this->getObjectManager()->get(ConfigPatch::class);
        $defaultConfigData = $installClass->getDefaultConfigData();

        foreach ($configs as $config) {
            $value = (string) $defaultConfigData[$config];
            $this->setConfig($config, $value);
        }
    }

    protected function setConfig(
        $path,
        $value,
        $scopeCode = 'default'
    ) {
        $this->getObjectManager()->get(\Magento\Framework\App\Config\MutableScopeConfigInterface::class)->setValue(
            $path,
            $value,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    protected function assertConfigInDb(
        string $path,
        mixed  $value,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int    $scopeId = 0
    ): void
    {
        $connection = $this->objectManager->create(\Magento\Framework\App\ResourceConnection::class)
            ->getConnection();

        $select = $connection->select()
            ->from('core_config_data', 'value')
            ->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId);

        $configValue = $connection->fetchOne($select);

        $this->assertEquals($value, $configValue);
    }

    /**
     * If testing classes that use WriterInterface under the hood to update the database
     * then you need a way to refresh the in-memory cache
     * This function achieves that while preserving the original bootstrap config
     */
    protected function refreshConfigFromDb(): void
    {
        $bootstrap = $this->getBootstrapConfig();
        $this->objectManager->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
        $this->setConfigFromArray($bootstrap);
    }

    /**
     * @return array<string, string>
     */
    protected function getBootstrapConfig(): array
    {
        $config = $this->objectManager->get(ScopeConfigInterface::class);

        $bootstrap = [
            ConfigHelper::APPLICATION_ID,
            ConfigHelper::SEARCH_ONLY_API_KEY,
            ConfigHelper::API_KEY,
            ConfigHelper::INDEX_PREFIX
        ];

        return array_combine(
            $bootstrap,
            array_map(
                fn($setting) => $config->getValue($setting, ScopeInterface::SCOPE_STORE),
                $bootstrap
            )
        );
    }

    /**
     * @param array<string, string> $settings
     * @return void
     */
    protected function setConfigFromArray(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->setConfig($key, $value);
            $this->setConfig($key, $value, 'admin');
        }
    }

    protected function clearIndices()
    {
        $indices = $this->algoliaConnector->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos((string) $name, $this->indexPrefix) === 0) {
                try {
                    $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($name);
                    $this->algoliaConnector->deleteIndex($indexOptions);
                } catch (AlgoliaException) {
                    // Might be a replica
                }
            }
        }
    }

    /** @return \Magento\Framework\ObjectManagerInterface */
    protected function getObjectManager()
    {
        return Bootstrap::getObjectManager();
    }

    private function bootstrap()
    {
        if ($this->boostrapped === true) {
            return;
        }

        $this->objectManager = $this->getObjectManager();
        $this->productMetadata = $this->objectManager->get(ProductMetadataInterface::class);

        if (version_compare($this->getMagentoVersion(), '2.4.7', '<')) {
            if ($this->getMagentEdition() === 'Community') {
                $this->assertValues = new Magento246CE();
            } else {
                $this->assertValues = new Magento246EE();
            }
        } else {
            if ($this->getMagentEdition() === 'Community') {
                $this->assertValues = new Magento247CE();
            } else {
                $this->assertValues = new Magento247EE();
            }
        }

        $this->configHelper = $this->getObjectManager()->create(ConfigHelper::class);

        $this->indexPrefix =  'magento2_' . date('Y-m-d_H:i:s') . '_' . (getenv('INDEX_PREFIX') ?: 'circleci_');

        // Admin
        $this->setConfig('algoliasearch_credentials/credentials/application_id', getenv('ALGOLIA_APPLICATION_ID'), 'admin');
        $this->setConfig('algoliasearch_credentials/credentials/search_only_api_key', getenv('ALGOLIA_SEARCH_KEY'), 'admin');
        $this->setConfig('algoliasearch_credentials/credentials/api_key', getenv('ALGOLIA_API_KEY'), 'admin');
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix, 'admin');
        // Default website
        $this->setConfig('algoliasearch_credentials/credentials/application_id', getenv('ALGOLIA_APPLICATION_ID'));
        $this->setConfig('algoliasearch_credentials/credentials/search_only_api_key', getenv('ALGOLIA_SEARCH_KEY'));
        $this->setConfig('algoliasearch_credentials/credentials/api_key', getenv('ALGOLIA_API_KEY'));
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix);

        $this->indexOptionsBuilder = $this->objectManager->get(IndexOptionsBuilder::class);
        $this->algoliaConnector = $this->objectManager->get(AlgoliaConnector::class);
        $this->indexNameFetcher = $this->objectManager->get(IndexNameFetcher::class);

        $this->boostrapped = true;
    }


    /**
     * @throws \ReflectionException
     */
    protected function mockProperty(object $object, string $propertyName, string $propertyClass): void
    {
        $mock = $this->createMock($propertyClass);
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($object, $mock);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array $parameters array of parameters to pass into method
     *
     * @throws \ReflectionException
     *
     * @return mixed method return
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object::class);
        return $reflection->getMethod($methodName)->invokeArgs($object, $parameters);
    }

    private function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    private function getMagentEdition()
    {
        return $this->productMetadata->getEdition();
    }

    protected function getSerializer()
    {
        return $this->getObjectManager()->get(\Magento\Framework\Serialize\SerializerInterface::class);
    }

    /**
     * Run a callback once and only once
     * @param callable $callback
     * @param string|null $key - a unique key for this operation - if null a unique key will be derived
     * @return mixed
     */
    function runOnce(callable $callback, ?string $key = null): mixed
    {
        static $executed = [];
        $key ??= is_string($callback) ? $callback : spl_object_hash((object) $callback);
        if (!isset($executed[$key])) {
            $executed[$key] = true;
            return $callback();
        }

        return null;
    }
}
