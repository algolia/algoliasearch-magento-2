<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Setup\UpgradeSchema;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento_2_01;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento_2_2;
use Algolia\AlgoliaSearch\Test\Integration\AssertValues\Magento_2_3;
use AlgoliaSearch\AlgoliaException;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

if (class_exists('PHPUnit\Framework\TestCase')) {
    class_alias('PHPUnit\Framework\TestCase', '\TC');
} else {
    class_alias('\PHPUnit_Framework_TestCase', '\TC');
}

if (class_exists('\Algolia\AlgoliaSearch\Test\Integration\AssertValues_2_01')) {
    class_alias('\Algolia\AlgoliaSearch\Test\Integration\AssertValues_2_01', 'AssertValues');
}

abstract class TestCase extends \TC
{
    /** @var bool */
    private $boostrapped = false;

    /** @var string */
    protected $indexPrefix;

    /** @var AlgoliaHelper */
    protected $algoliaHelper;

    /** @var ConfigHelper */
    protected $configHelper;

    /** @var Magento_2_01|Magento_2_2 */
    protected $assertValues;

    public function setUp()
    {
        $this->bootstrap();
    }

    public function tearDown()
    {
        $this->clearIndices();
    }

    protected function resetConfigs($configs = [])
    {
        /** @var UpgradeSchema $installClass */
        $installClass = $this->getObjectManager()->get('Algolia\AlgoliaSearch\Setup\UpgradeSchema');
        $defaultConfigData = $installClass->getDefaultConfigData();

        foreach ($configs as $config) {
            $value = (string) $defaultConfigData[$config];
            $this->setConfig($config, $value);
        }
    }

    protected function setConfig($path, $value)
    {
        $this->getObjectManager()->get('Magento\Framework\App\Config\MutableScopeConfigInterface')->setValue(
            $path,
            $value,
            ScopeInterface::SCOPE_STORE,
            'default'
        );
    }

    protected function clearIndices()
    {
        $indices = $this->algoliaHelper->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix) === 0) {
                try {
                    $this->algoliaHelper->deleteIndex($name);
                } catch (AlgoliaException $e) {
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

        if (version_compare($this->getMagentoVersion(), '2.2.0', '<')) {
            $this->assertValues = new Magento_2_01();
        } elseif (version_compare($this->getMagentoVersion(), '2.3.0', '<')) {
            $this->assertValues = new Magento_2_2();
        } else {
            $this->assertValues = new Magento_2_3();
        }

        $this->algoliaHelper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\AlgoliaHelper');

        $this->configHelper = $config = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\ConfigHelper');

        $this->setConfig('algoliasearch_credentials/credentials/application_id', getenv('ALGOLIA_APPLICATION_ID'));
        $this->setConfig('algoliasearch_credentials/credentials/search_only_api_key', getenv('ALGOLIA_SEARCH_API_KEY'));
        $this->setConfig('algoliasearch_credentials/credentials/api_key', getenv('ALGOLIA_API_KEY'));

        $this->indexPrefix =  'TRAVIS_M2_' . getmypid() . (getenv('INDEX_PREFIX') ?: 'magento20tests_');
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix);

        $this->boostrapped = true;
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
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function getMagentoVersion()
    {
        /** @var \Magento\Framework\App\ProductMetadataInterface $productMetadata */
        $productMetadata = $this->getObjectManager()->get('\Magento\Framework\App\ProductMetadataInterface');

        return $productMetadata->getVersion();
    }
}
