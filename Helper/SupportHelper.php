<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;

class SupportHelper
{
    const INTERNAL_API_PROXY_URL = 'https://lj1hut7upg.execute-api.us-east-2.amazonaws.com/dev/';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AdapterInterface */
    private $dbConnection;

    /** @var StoreManagerInterface */
    private $storeManager;

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
     */
    public function __construct(ConfigHelper $configHelper, ResourceConnection $resourceConnection, StoreManagerInterface $storeManager)
    {
        $this->configHelper = $configHelper;
        $this->dbConnection = $resourceConnection->getConnection('core_read');
        $this->storeManager = $storeManager;

        $this->queueTable = $resourceConnection->getTableName('algoliasearch_queue');
        $this->configTable = $resourceConnection->getTableName('core_config_data');
        $this->queueArchiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');
        $this->catalogTable = $resourceConnection->getTableName('catalog_product_entity');
        $this->modulesTable = $resourceConnection->getTableName('setup_module');
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
            'noteText' => $this->getNoteText($data['send_additional_info']),
        ];

        return $this->pushMessage($messageData);
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
    {
        $appId = $this->configHelper->getApplicationID();
        $apiKey = $this->configHelper->getAPIKey();

        $token = $appId . ':' . $apiKey;
        $token = base64_encode($token);
        $token = str_replace(["\n", '='], '', $token);

        $params = [
            'appId' => $appId,
            'token' => $token,
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $res = curl_exec($ch);

        curl_close($ch);

        if ($res) {
            $res = json_decode($res, true);
        }

        return $res['extension_support'];
    }

    /**
     * @param bool $sendAdditionalData
     *
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getNoteText($sendAdditionalData = false)
    {
        $noteText = [];

        $noteText[] = $this->getGeneralMagentoInfo();
        $noteText[] = $this->getQueueInfo();
        $noteText[] = $this->getQueueArchiveInfo();
        $noteText[] = $this->getAlgoliaConfiguration();

        if ($sendAdditionalData === true) {
            $noteText[] = $this->getCatalogInfo();
            $noteText[] = $this->get3rdPartyModules();
        }

        $noteText = implode('<br><br>', $noteText);

        return $noteText;
    }

    /** @return string */
    private function getGeneralMagentoInfo()
    {
        $magentoInfo = [];

        $magentoInfo[] = '<b>Extension version:</b> ' . $this->getExtensionVersion();
        $magentoInfo[] = '<b>Magento version:</b> ' . $this->configHelper->getMagentoVersion();
        $magentoInfo[] = '<b>Magento edition:</b> ' . $this->configHelper->getMagentoEdition();

        return implode('<br>', $magentoInfo);
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getQueueInfo()
    {
        $queueInfoText = [];

        $query = 'SELECT COUNT(*) as `count`, MIN(created) as `oldest` FROM ' . $this->queueTable;
        $queueInfo = $this->dbConnection->query($query)->fetch();

        $queueInfoText[] = '<b>Number of jobs in indexing queue:</b> ' . $queueInfo['count'];
        $queueInfoText[] = '<b>Oldest job in indexing queue was created at:</b> ' . $queueInfo['oldest'];

        return implode('<br>', $queueInfoText);
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
            $queueArchiveInfo[] = '<b>Queue archive rows (20 newest rows):</b>';

            $firstRow = reset($archiveRows);
            $headers = array_keys($firstRow);
            $noteText[] = implode(' || ', $headers);

            $archiveRows = array_map(function ($row) {
                return implode(' || ', $row);
            }, $archiveRows);

            $queueArchiveInfo = array_merge($queueArchiveInfo, $archiveRows);
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
        $catalogInfoText = ['<b>Catalog size (by product type):</b>'];

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
            $modulesText[] = '<b>3rd party modules:</b>';
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

    /**
     * @param array $messageData
     *
     * @return bool
     */
    private function pushMessage($messageData)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL . 'hs-push/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $res = curl_exec($ch);

        curl_close($ch);

        if ($res === 'true') {
            return true;
        }

        return false;
    }
}
