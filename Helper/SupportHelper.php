<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;

class SupportHelper
{
    const INTERNAL_API_PROXY_URL = 'https://magento-proxy.algolia.com/';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AdapterInterface */
    private $dbConnection;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var string */
    private $queueTable;

    /** @var string */
    private $queueArchiveTable;

    /** @var string */
    private $configTable;

    /** @var string */
    private $catalogTable;

    /** @var string */
    private $modulesTable;

    /**
     * @param ConfigHelper $configHelper
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param ProxyHelper $proxyHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        ProxyHelper $proxyHelper
    ) {
        $this->configHelper = $configHelper;
        $this->dbConnection = $resourceConnection->getConnection('core_read');
        $this->storeManager = $storeManager;
        $this->proxyHelper = $proxyHelper;

        $this->queueTable = $resourceConnection->getTableName('algoliasearch_queue');
        $this->configTable = $resourceConnection->getTableName('core_config_data');
        $this->queueArchiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');
        $this->catalogTable = $resourceConnection->getTableName('catalog_product_entity');
        $this->modulesTable = $resourceConnection->getTableName('setup_module');
    }

    /** @return string */
    public function getApplicationId()
    {
        return $this->configHelper->getApplicationID();
    }

    /** @return string */
    public function getExtensionVersion()
    {
        return $this->configHelper->getExtensionVersion();
    }

    /**
     * @param array $data
     *
     * @throws \Zend_Db_Statement_Exception
     *
     * @return bool
     */
    public function processContactForm($data)
    {
        list($firstname, $lastname) = $this->splitName($data['name']);

        $messageData = [
            'email' => $data['email'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'subject' => $data['subject'],
            'text' => $data['message'],
            'note' => $this->getNoteData($data['send_additional_info']),
        ];

        return $this->proxyHelper->pushSupportTicket($messageData);
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_EXTENSION_SUPPORT);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('extension_support', $info)) {
            return $info['extension_support'];
        }

        return true;
    }

    /**
     * @param bool $sendAdditionalData
     *
     * @throws \Zend_Db_Statement_Exception
     *
     * @return array
     */
    private function getNoteData($sendAdditionalData = false)
    {
        $queueInfo = $this->getQueueInfo();

        $noteData = [
            'extension_version' => $this->getExtensionVersion(),
            'magento_version' => $this->configHelper->getMagentoVersion(),
            'magento_edition' => $this->configHelper->getMagentoEdition(),
            'queue_jobs_count' => $queueInfo['count'],
            'queue_oldest_job' => $queueInfo['oldest'],
            'queue_archive_rows' => $this->getQueueArchiveInfo(),
            'algolia_configuration' => $this->getAlgoliaConfiguration(),
        ];

        if ($sendAdditionalData === true) {
            $noteData['catalog_info'] = $this->getCatalogInfo();
            $noteData['modules'] = $this->get3rdPartyModules();
        }

        return $noteData;
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return array
     */
    private function getQueueInfo()
    {
        $query = 'SELECT COUNT(*) as `count`, MIN(created) as `oldest` FROM ' . $this->queueTable;
        $queueInfo = $this->dbConnection->query($query)->fetch();

        if (!$queueInfo['oldest']) {
            $queueInfo['oldest'] = '[no jobs in indexing queue]';
        }

        return $queueInfo;
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getQueueArchiveInfo()
    {
        $queueArchiveInfo = [];

        $query = 'SELECT * 
          FROM ' . $this->queueArchiveTable . ' 
          ORDER BY created_at DESC
          LIMIT 20';

        $archiveRows = $this->dbConnection->query($query)->fetchAll();
        if ($archiveRows) {
            $firstRow = reset($archiveRows);
            $headers = array_keys($firstRow);
            $noteText[] = implode(' || ', $headers);

            $archiveRows = array_map(function ($row) {
                return implode(' || ', $row);
            }, $archiveRows);

            $queueArchiveInfo = array_merge($queueArchiveInfo, $archiveRows);
        }

        if ($queueArchiveInfo === []) {
            return '[no rows in archive table]';
        }

        return implode('<br>', $queueArchiveInfo);
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getAlgoliaConfiguration()
    {
        $configurationText = [];
        $defaultConfigValues = [];

        $configRows = $this->dbConnection->query($this->getConfigurationQuery())
                                         ->fetchAll(\PDO::FETCH_KEY_PAIR);

        $configurationText[] = '<b>Algolia configuration (default):</b>';
        foreach ($configRows as $path => $value) {
            $value = $this->getConfigurationValue($value);

            $configurationText[] = $path . ' => ' . $value;
            $defaultConfigValues[$path] = $value;
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $configRows = $this->dbConnection->query($this->getConfigurationQuery($storeId))
                                             ->fetchAll(\PDO::FETCH_KEY_PAIR);

            $differentStoreConfigValues = [];
            foreach ($configRows as $path => $value) {
                $value = $this->getConfigurationValue($value);

                if ($defaultConfigValues[$path] !== $value) {
                    $differentStoreConfigValues[] = $path . ' => ' . $value;
                }
            }

            if ($differentStoreConfigValues !== []) {
                $configurationText[] = '<br>'; // Separator from previous config section
                $configurationText[] = '<b>Algolia configuration (STORE ID ' . $storeId . '):</b>';
                $configurationText = array_merge($configurationText, $differentStoreConfigValues);
            }
        }

        return implode('<br>', $configurationText);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    private function getConfigurationQuery($storeId = null)
    {
        $scope = 'default';
        $scopeId = 0;

        if ($storeId !== null) {
            $scope = 'stores';
            $scopeId = $storeId;
        }

        $query = 'SELECT 
            path, 
            value 
          FROM 
            ' . $this->configTable . ' 
          WHERE 
            scope = "' . $scope . '"
            AND scope_id = ' . $scopeId . '
            AND path LIKE "algoliasearch_%"
            AND path != "algoliasearch_credentials/credentials/api_key"';

        return $query;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function getConfigurationValue($value)
    {
        $value = json_decode($value, true) ?: $value;
        $value = var_export($value, true);

        return $value;
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getCatalogInfo()
    {
        $catalogInfoText = [];

        $query = 'SELECT type_id, COUNT(*) as `count` FROM ' . $this->catalogTable . ' GROUP BY type_id';
        $catalogInfo = $this->dbConnection->query($query)->fetchAll(\PDO::FETCH_KEY_PAIR);

        $total = 0;
        foreach ($catalogInfo as $type => $count) {
            $total += $count;

            $catalogInfoText[] = $type . ': ' . number_format($count, 0, ',', ' ');
        }

        $catalogInfoText[] = 'Total number: ' . number_format($total, 0, ',', ' ');

        return implode('<br>', $catalogInfoText);
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function get3rdPartyModules()
    {
        $modulesText = [];

        $query = 'SELECT * 
          FROM ' . $this->modulesTable . ' 
          WHERE module NOT LIKE "Magento\_%"
          ORDER BY module';

        $modules = $this->dbConnection->query($query)->fetchAll();
        if ($modules) {
            $firstRow = reset($modules);
            $headers = array_keys($firstRow);
            $modulesText[] = implode(' || ', $headers);

            $modules = array_map(function ($row) {
                return implode(' || ', $row);
            }, $modules);

            $modulesText = array_merge($modulesText, $modules);
        }

        return implode('<br>', $modulesText);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    private function splitName($name)
    {
        return explode(' ', $name, 2);
    }
}
