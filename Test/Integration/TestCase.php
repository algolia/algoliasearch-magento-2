<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    private $boostrapped = false;

    protected $indexPrefix;

    /** @var AlgoliaHelper */
    protected $algoliaHelper;

    /** @var  ConfigHelper */
    protected $configHelper;

    public function setUp()
    {
        $this->bootstrap();
    }

    protected function resetConfigs($configs = [])
    {
        $configXmlFile = __DIR__.'/../../etc/config.xml';

        $xml = simplexml_load_file($configXmlFile);

        foreach ($configs as $config) {
            list($section, $subsection, $setting) = explode('/', $config);

            $element = $xml->xpath('//default/'.$section.'/'.$subsection.'/'.$setting);
            $value = (string) reset($element);

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

        $this->algoliaHelper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\AlgoliaHelper');

        $this->configHelper = $config = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\ConfigHelper');

        $this->setConfig('algoliasearch_credentials/credentials/application_id', getenv('APPLICATION_ID'));
        $this->setConfig('algoliasearch_credentials/credentials/search_only_api_key', getenv('SEARCH_ONLY_API_KEY'));
        $this->setConfig('algoliasearch_credentials/credentials/api_key', getenv('API_KEY'));

        $this->indexPrefix =  getenv('INDEX_PREFIX') ?: 'magento20tests_';
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix);

        $this->boostrapped = true;
    }
}
