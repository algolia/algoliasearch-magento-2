<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;

class NoteBuilder
{
    /** @var JobResourceModel */
    private $jobResourceModel;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AdapterInterface */
    private $dbConnection;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var string */
    private $queueArchiveTable;

    /** @var string */
    private $configTable;

    /** @var string */
    private $catalogTable;

    /** @var string */
    private $modulesTable;

    /**
     * @param JobResourceModel $jobResourceModel
     * @param ConfigHelper $configHelper
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        JobResourceModel $jobResourceModel,
        ConfigHelper $configHelper,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        $this->jobResourceModel = $jobResourceModel;
        $this->configHelper = $configHelper;
        $this->dbConnection = $resourceConnection->getConnection('core_read');
        $this->storeManager = $storeManager;

        $this->configTable = $resourceConnection->getTableName('core_config_data');
        $this->queueArchiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');
        $this->catalogTable = $resourceConnection->getTableName('catalog_product_entity');
        $this->modulesTable = $resourceConnection->getTableName('setup_module');
    }

    /**
     * @param bool $sendAdditionalData
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Statement_Exception
     *
     * @return array
     */
    public function getNote($sendAdditionalData = false)
    {
        $queueInfo = $this->jobResourceModel->getQueueInfo();

        $noteData = [
            'extension_version' => $this->configHelper->getExtensionVersion(),
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
     * @return string
     */
    private function getQueueArchiveInfo()
    {
        $queueArchiveInfo = [];

        $query = $this->dbConnection->select()
            ->from($this->queueArchiveTable)
            ->order('created_at DESC')
            ->limit(20);

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

        $query = $this->dbConnection->select()
            ->from($this->configTable, ['path', 'value'])
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId)
            ->where('path LIKE "algoliasearch_%"')
            ->where('path != "algoliasearch_credentials/credentials/api_key"');

        return $query;
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return string
     */
    private function getCatalogInfo()
    {
        $catalogInfoText = [];

        $query = $this->dbConnection->select()
            ->from($this->catalogTable, ['type_id', 'count' => 'COUNT(*)'])
            ->group('type_id');

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

        $query = $this->dbConnection->select()
            ->from($this->modulesTable)
            ->where('module NOT LIKE "Magento\_%"')
            ->order('module');

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
}
