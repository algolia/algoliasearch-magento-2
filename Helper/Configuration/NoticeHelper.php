<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\ExtensionNotification;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;

class NoticeHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CATALOG_SEARCH_MYSQL_ENGINE = 'mysql';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var ModuleManager */
    private $moduleManager;

    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var ExtensionNotification */
    private $extensionNotification;

    /** @var JobCollectionFactory */
    private $jobCollectionFactory;

    /** @var UrlInterface */
    protected $urlBuilder;

    /** @var string[] */
    protected $noticeFunctions = [
        'getQueueNotice',
        'getMsiNotice',
        'getEsNotice',
        'getVersionNotice',
    ];

    /** @var array[] */
    protected $notices;

    public function __construct(
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager,
        ExtensionNotification $extensionNotification,
        JobCollectionFactory $jobCollectionFactory,
        UrlInterface $urlBuilder
    ) {
        $this->configHelper = $configHelper;
        $this->proxyHelper = $proxyHelper;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->extensionNotification = $extensionNotification;
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->urlBuilder = $urlBuilder;

        foreach ($this->noticeFunctions as $noticeFunction) {
            call_user_func([$this, $noticeFunction]);
        }
    }

    public function getExtensionNotices()
    {
        return $this->notices;
    }

    protected function getQueueNotice()
    {
        $jobCollection = $this->jobCollectionFactory->create();
        $size = $jobCollection->getSize();
        $maxJobsPerSingleRun = $this->configHelper->getNumberOfJobToRun();

        $etaMinutes = ceil($size / $maxJobsPerSingleRun) * 5;

        $eta = $etaMinutes . ' minutes';
        if ($etaMinutes > 60) {
            $hours = floor($etaMinutes / 60);
            $restMinutes = $etaMinutes % 60;

            $eta = $hours . ' hours ' . $restMinutes . ' minutes';
        }

        $indexingQueueConfigUrl = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/algoliasearch_queue');
        $indexingQueuePageUrl = $this->urlBuilder->getUrl('algolia_algoliasearch/queue/index');

        if (!$this->configHelper->isQueueActive()) {
            $icon = 'icon-warning';
            $noticeTitle = '<a href="' . $indexingQueueConfigUrl . '">Indexing Queue</a> is not enabled';
            $noticeContent = 'It is highly recommended that you enable it, especially if you are on a production environment.
							<br><br>
							Find out more about Indexing Queue in <a href="https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link" target="_blank">documentation</a>.';
        } else {
            $icon = 'icon-bulb';
            $noticeTitle = 'Queued indexing jobs';
            $noticeContent = 'Number of queued jobs: <strong>' . $size . '</strong>.
                                Assuming your queue runner runs every 5 minutes, all jobs will be processed
                                in approx. ' . $eta . '.
                                You may want to <a href="' . $indexingQueuePageUrl . '">clear the queue</a> or <a href="' . $indexingQueueConfigUrl . '">configure indexing queue</a>.
                                <br><br>
                                Find out more about Indexing Queue in <a href="https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link" target="_blank">documentation</a>.';
        }

        $this->notices[] = [
            'selector' => '.entry-edit',
            'method' => 'before',
            'message' => $this->formatNotice($noticeTitle, $noticeContent, $icon),
        ];
    }

    protected function getMsiNotice()
    {
        if (! $this->isMsiExternalModuleNeeded()) {
            return;
        }

        $noticeTitle = 'Magento Multi-source Inventory compatibility';
        $noticeContent = 'Your store is using Magento Multi-source Inventory and for Algolia to be fully compatible with it, you should install the module <a target="_blank" href="https://github.com/algolia/algoliasearch-inventory-magento-2/">Algoliasearch Inventory</a>.<br>
						More information in <a href="https://www.algolia.com/doc/integration/magento-2/guides/multi-source-inventory/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link" target="_blank">documentation</a>.
						<br>';

        $this->notices[] = [
            'selector' => '.entry-edit',
            'method' => 'before',
            'message' => $this->formatNotice($noticeTitle, $noticeContent),
        ];
    }

    protected function getEsNotice()
    {
        if ($this->isMysqlUsed()) {
            $noticeContent = '<span class="algolia-config-warning">&#9888;</span> If you turn on this feature, please make sure that every attribute you choose in the facet listing below is configured as "<strong>Use in Layered Navigation</strong>" for categories and "<strong>Use in Search Result Layered Navigation</strong>" for Catalog Search in <strong>Stores > Attributes > Product > "Storefront Properties" panel of attribute configuration</strong>';
        } else {
            $noticeContent = '<span class="algolia-config-warning">&#9888;</span>This feature is currently  supported only for MySQL search engine.';
        }

        $this->notices[] = [
            'selector' => '#row_algoliasearch_instant_instant_backend_rendering_enable .note',
            'method' => 'append',
            'message' => $noticeContent,
        ];
    }

    protected function getVersionNotice()
    {
        $newVersion = $this->getNewVersionNotification();
        if ($newVersion === null) {
            return;
        }

        $noticeTitle = 'Algolia Extension update';
        $noticeContent = 'You are using old version of Algolia extension. Latest version of the extension is v <b>' . $newVersion['version'] . '</b><br />
							It is highly recommended to update your version to avoid any unexpecting issues and to get new features.<br />
							See details on our <a target="_blank" href="' . $newVersion['url'] . '">Github repository</a>.';

        $this->notices[] = [
            'selector' => '.entry-edit',
            'method' => 'before',
            'message' => $this->formatNotice($noticeTitle, $noticeContent),
        ];
    }

    protected function formatNotice($title, $content, $icon = 'icon-warning')
    {
        return '<div class="algolia_block ' . $icon . '">
                    <div class="heading">' . $title . '</div>
                    ' . $content . '
                </div>';
    }

    /** @return bool */
    public function isQueryRulesEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_QUERY_RULES);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('query_rules', $info)) {
            return $info['query_rules'];
        }

        return true;
    }

    /** @return bool */
    public function isClickAnalyticsEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_ANALYTICS);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('click_analytics', $info)) {
            return $info['click_analytics'];
        }

        return true;
    }

    public function isMysqlUsed()
    {
        return $this->configHelper->getCatalogSearchEngine() === self::CATALOG_SEARCH_MYSQL_ENGINE;
    }

    public function isMsiExternalModuleNeeded()
    {
        // If Magento Inventory is not installed, no need for the external module
        $hasMsiModule = $this->moduleManager->isEnabled('Magento_Inventory');
        if (! $hasMsiModule) {
            return false;
        }

        // If the external module is already installed, no need to do it again
        $hasMsiExternalModule = $this->moduleManager->isEnabled('Algolia_AlgoliaSearchInventory');
        if ($hasMsiExternalModule) {
            return false;
        }

        // Module installation is only needed if there's more than one source
        $sourceCollection = $this->objectManager->create('\Magento\Inventory\Model\ResourceModel\Source\Collection');
        if ($sourceCollection->getSize() <= 1) {
            return false;
        }

        return true;
    }

    /** @return array|null */
    public function getNewVersionNotification()
    {
        return $this->extensionNotification->checkVersion();
    }
}
