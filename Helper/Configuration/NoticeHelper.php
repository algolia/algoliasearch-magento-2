<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\ExtensionNotification;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

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

    /** @var AssetRepository */
    protected $assetRepository;

    /** @var string[] */
    protected $noticeFunctions = [
        'getQueueNotice',
        'getMsiNotice',
        'getEsNotice',
        'getVersionNotice',
        'getClickAnalyticsNotice',
        'getQueryRulesNotice',
        'getPersonalizationNotice',
    ];

    /** @var array[] */
    protected $pagesWithoutQueueNotice = [
        'algoliasearch_cc_analytics',
        'algoliasearch_analytics',
        'algoliasearch_personalization',
        'algoliasearch_advanced',
        'algoliasearch_extra_settings',
    ];

    /** @var array[] */
    protected $notices;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager,
        ExtensionNotification $extensionNotification,
        JobCollectionFactory $jobCollectionFactory,
        UrlInterface $urlBuilder,
        AssetRepository $assetRepository
    ) {
        $this->configHelper = $configHelper;
        $this->proxyHelper = $proxyHelper;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->extensionNotification = $extensionNotification;
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->urlBuilder = $urlBuilder;
        $this->assetRepository = $assetRepository;

        foreach ($this->noticeFunctions as $noticeFunction) {
            call_user_func([$this, $noticeFunction]);
        }
        parent::__construct($context);
    }

    public function getExtensionNotices()
    {
        return $this->notices;
    }

    protected function getQueueNotice()
    {
        foreach ($this->pagesWithoutQueueNotice as $page) {
            if (preg_match('/' . $page . '/', $this->urlBuilder->getCurrentUrl())) {
                return;
            }
        }

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

    protected function getClickAnalyticsNotice()
    {
        // If the feature is enabled both in Magento Admin and Algolia dashboard, no need to display a notice
        if ($this->isClickAnalyticsEnabled() && $this->configHelper->isClickConversionAnalyticsEnabled()) {
            return;
        }

        $noticeContent = '';
        $selector = '';
        $method = 'before';

        // If the feature is not enabled in the Algolia dashboard
        if (! $this->isClickAnalyticsEnabled()) {
            $noticeContent = '<br>
				<div class="algolia_block icon-stars">
					To get access to this Algolia feature,
					please consider <a href="https://www.algolia.com/billing/overview" target="_blank">upgrading to a higher plan</a>.
				</div>';
            $selector = '#algoliasearch_cc_analytics_cc_analytics_group .comment';
            $method = 'after';
        }

        // If the feature is enabled in the Algolia dashboard but not activated on the Magento Admin
        if ($this->isClickAnalyticsEnabled() && ! $this->configHelper->isClickConversionAnalyticsEnabled()) {
            $noticeContent = '<tr>
				<td colspan="3">
					<div class="algolia_block blue icon-stars">
					Enhance your Analytics with <b>Algolia Click Analytics</b> that provide you even more insights
					like Click-through Rate, Conversion Rate from searches and average click position.
					Click Analytics are only available for higher plans and require only minor additional settings.
					<br><br>
					Find more information in <a href="https://www.algolia.com/doc/integration/magento-2/how-it-works/click-and-conversion-analytics/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link" target="_blank">documentation</a>.
					</div>
				</td>
			</tr>';
            $selector = '#row_algoliasearch_cc_analytics_cc_analytics_group_enable';
            $method = 'before';
        }

        $this->notices[] = [
            'selector' => $selector,
            'method' => $method,
            'message' => $noticeContent,
        ];
    }

    protected function getQueryRulesNotice()
    {
        if ($this->isQueryRulesEnabled()) {
            return;
        }

        $noticeContent = '<br>
                <div class="algolia_block icon-stars">
                    To be able to create Query rules for facets,
                    please consider <a href="https://www.algolia.com/billing/overview" target="_blank">upgrading to a higher plan</a>.
                </div>';

        $this->notices[] = [
            'selector' => '#row_algoliasearch_instant_instant_facets .algolia_block.blue',
            'method' => 'after',
            'message' => $noticeContent,
        ];
    }

    protected function getPersonalizationNotice()
    {
        if (! preg_match('/algoliasearch_personalization/', $this->urlBuilder->getCurrentUrl())) {
            return;
        }

        $personalizationStatus = $this->getPersonalizationStatus();

        // Adding header
        $docContent = '<h2 class="algolia-perso-title">Personalization</h2>';

        if ($personalizationStatus < 2) {
            $docContent .=  '<div class="perso-illustration">
                <img src="' . $this->assetRepository->getUrl('Algolia_AlgoliaSearch::images/illu-perso.svg') . '"/>
            </div>';
        }

        $docContent .= '<div class="algolia_block icon-documentation algoblue">
            <div class="heading"></div>
            Personalization brings another level of relevant search results to your customers.<br/>
            Find out more in our <a href="https://www.algolia.com/doc/guides/getting-insights-and-analytics/personalization/what-is-personalization/" target="_blank`">Documentation</a>.
        </div>';

        switch ($personalizationStatus) {
            // Activated
            case 2: $warningContent = 'Personalization is based on actions a user has performed in the past. We help you collect some of the data automatically.</br>
        After you\'ve collected a reasonable amount of data, Personlization can be applied.';
                $icon = 'icon-warning';
                break;
            // Available but not activated
            case 1: $warningContent = 'To start using this feature, please head over the <a href="https://www.algolia.com/dashboard" target="_blank`">Algolia Dashboard</a>, 
        and make sure you\'ve enabled Personalization in your account, as well as agreed to the terms and conditions of using Personalization.';
                $icon = 'icon-warning';
                break;
            // Not Available
            default: $warningContent = 'To get access to this Algolia feature, please <a target="_blank" href="https://www.algolia.com/contact/enterprise/">contact us</a>.';
                $icon = 'icon-stars';
                break;
        }

        $docContent .= $this->formatNotice('', $warningContent, $icon);

        $this->notices[] = [
            'selector' => '.entry-edit',
            'method' => 'before',
            'message' => $docContent,
        ];

        // Adding footer
        $footerContent = '<div class="algolia-perso-footer"><br/><h2>Personlization preferences</h2>
        <p>Manage your Personalization further on the <a href="https://www.algolia.com/dashboard" target="_blank`">Algolia Dashboard</a></p></div>';

        $this->notices[] = [
            'selector' => '#algoliasearch_personalization_personalization_group_personalization_conversion_events_group',
            'method' => 'after',
            'message' => $footerContent,
        ];
    }

    protected function formatNotice($title, $content, $icon = 'icon-warning')
    {
        return '<div class="algolia_block ' . $icon . '">
                    <div class="heading">' . $title . '</div>
                    ' . $content . '
                </div>';
    }

    /**
     * 0 for non available
     * 1 for available but not activated
     * 2 for activated
     *
     * @return int
     */
    public function getPersonalizationStatus()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_PERSONALIZATION);

        $status = 2;

        if ($info
            && array_key_exists('personalization', $info)
            && array_key_exists('personalization_enabled_at', $info)) {
            if (!$info['personalization']) {
                $status = 0;
            }
            if ($info['personalization_enabled_at'] === null) {
                $status = min(1, $status);
            }
        }

        return $status;
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
