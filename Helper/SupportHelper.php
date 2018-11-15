<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class SupportHelper
{
    const INTERNAL_API_PROXY_URL = 'https://lj1hut7upg.execute-api.us-east-2.amazonaws.com/dev/';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AdapterInterface */
    private $dbConnection;

    /** @var string */
    private $queueTable;

    /** @var string */
    private $queueArchiveTable;

    /** @var string */
    private $configTable;

    /** @var string */
    private $modulesTable;

    /**
     * @param ConfigHelper $configHelper
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ConfigHelper $configHelper, ResourceConnection $resourceConnection)
    {
        $this->configHelper = $configHelper;
        $this->dbConnection = $resourceConnection->getConnection('core_read');

        $this->queueTable = $resourceConnection->getTableName('algoliasearch_queue');
        $this->configTable = $resourceConnection->getTableName('core_config_data');
        $this->queueArchiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');
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
     * @return bool
     * @throws \Zend_Db_Statement_Exception
     */
    public function processContactForm($data)
    {
        $data = $this->getMessageData($data);

        list($firstname, $lastname) = $this->splitName($data['name']);

        $messageData = [
            'email' => $data['email'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'subject' => $data['subject'],
            'text' => $data['message'],
            'noteText' => $this->getNoteText(),
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
     * @param array $data
     *
     * @return array
     */
    private function getMessageData($data)
    {
        $attributes = ['name', 'email', 'subject', 'message'];

        $cleanData = [];

        foreach ($attributes as $attribute) {
            $cleanData[$attribute] = $data[$attribute];
        }

        return $cleanData;
    }

    /**
     * @return string
     * @throws \Zend_Db_Statement_Exception
     */
    private function getNoteText()
    {
        $noteText = '';

        $noteText .= '<b>Extension version:</b> ' . $this->getExtensionVersion() . '<br>';
        $noteText .= '<b>Magento version:</b> ' . $this->configHelper->getMagentoVersion() . '<br>';
        $noteText .= '<b>Magento edition:</b> ' . $this->configHelper->getMagentoEdition() . '<br><br>';

        $query = 'SELECT COUNT(*) as `count`, MIN(created) as `oldest` FROM ' . $this->queueTable;
        $queueInfo = $this->dbConnection->query($query)->fetch();

        $noteText .= '<b>Number of jobs in indexing queue:</b> ' . $queueInfo['count'] . '<br>';
        $noteText .= '<b>Oldest job in indexing queue was created at:</b> ' . $queueInfo['oldest'] . '<br><br>';

        $query = 'SELECT * 
          FROM '.$this->queueArchiveTable.' 
          ORDER BY created_at DESC
          LIMIT 20';

        $archiveRows = $this->dbConnection->query($query)->fetchAll();
        if ($archiveRows)
        {
            $noteText .= "<b>Queue archive rows (20 newest rows):</b><br>";

            $firstRow = reset($archiveRows);
            $headers = array_keys($firstRow);
            $noteText .= implode(' || ', $headers) . '<br>';

            $archiveRows = array_map(function($row) {
                return implode(' || ', $row);
            }, $archiveRows);

            $noteText .= implode('<br>', $archiveRows);
            $noteText .= '<br><br>';
        }

        $query = 'SELECT 
            path, 
            value 
          FROM 
            '.$this->configTable.' 
          WHERE 
            path LIKE "algoliasearch_%"
            AND path != "algoliasearch_credentials/credentials/api_key"';

        $configRows = $this->dbConnection->query($query)
                                         ->fetchAll(\PDO::FETCH_KEY_PAIR);

        $noteText .= "<b>Algolia configuration (default):</b><br>";
        foreach ($configRows as $path => $value) {
            $value = json_decode($value, true) ?: $value;
            $value = var_export($value, true);

            $noteText .= $path . ' => ' . $value . '<br>';
        }

        $noteText .= '<br><br>';

        $query = 'SELECT * 
          FROM '.$this->modulesTable.' 
          WHERE module NOT LIKE "Magento\_%"
          ORDER BY module';

        $modules = $this->dbConnection->query($query)->fetchAll();
        if ($modules) {
            $noteText .= "<b>3rd party modules:</b><br>";
            $firstRow = reset($modules);
            $headers = array_keys($firstRow);
            $noteText .= implode(' || ', $headers) . '<br>';

            $modules = array_map(function($row) {
                return implode(' || ', $row);
            }, $modules);

            $noteText .= implode('<br>', $modules);
            $noteText .= '<br><br>';
        }

        return $noteText;
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
