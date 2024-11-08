<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\ExtensionNotification;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

class NoticeHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var string[] */
    protected $noticeFunctions = [
        'getQueueNotice',
        'getMsiNotice',
        'getVersionNotice',
        'getClickAnalyticsNotice',
        'getPersonalizationNotice',
        'getRecommendNotice',
        'getCookieConfigurationNotice',
        'getMultiApplicationIDsNotice',
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
        protected \Magento\Framework\App\Helper\Context $context,
        protected ConfigHelper $configHelper,
        protected PersonalizationHelper $personalizationHelper,
        protected ModuleManager $moduleManager,
        protected ObjectManagerInterface $objectManager,
        protected ExtensionNotification $extensionNotification,
        protected JobCollectionFactory $jobCollectionFactory,
        protected UrlInterface $urlBuilder,
        protected AssetRepository $assetRepository,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {
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

    protected function getVersionNotice(): void
    {
        $currentVersion = $this->configHelper->getExtensionVersion();
        $newVersion = $this->getNewVersionNotification();
        if (!$currentVersion && !$newVersion) {
            return;
        }

        $notice = [
            'selector' => '.entry-edit',
            'method' => 'before'
        ];

        if ($newVersion) {
            $noticeTitle = 'Algolia extension update';
            $noticeContent = 'You are using an old version of Algolia extension. Latest version of the extension is v <b>' . $newVersion['version'] . '</b><br />
							It is highly recommended to update your version to avoid any unexpected issues and to get new features.<br />
							See details on our <a target="_blank" href="' . $newVersion['url'] . '">Github repository</a>.';
            $notice['message'] = $this->formatNotice($noticeTitle, $noticeContent);
        }
        else {
            $noticeTitle = 'Algolia extension version';
            $noticeContent = "You are using version <strong>$currentVersion</strong> of the Algolia Magento integration.";
            $notice['message'] = $this->formatNotice($noticeTitle, $noticeContent, 'icon-bulb');
        }

        $this->notices[] = $notice;
    }

    protected function getClickAnalyticsNotice()
    {
        // If the feature is enabled both in Magento Admin and Algolia dashboard, no need to display a notice
        if ($this->configHelper->isClickConversionAnalyticsEnabled()) {
            return;
        }

        $noticeContent = '';
        $selector = '';
        $method = 'before';

        // If the feature is enabled in the Algolia dashboard but not activated on the Magento Admin
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

        $this->notices[] = [
            'selector' => $selector,
            'method' => $method,
            'message' => $noticeContent,
        ];
    }

   protected function getCookieConfigurationNotice()
   {
        $noticeContent = '';
        $selector = '';
        $method = 'after';

        // If the feature is enabled in the Algolia dashboard but not activated on the Magento Admin
        $noticeContent = '
                <div class="algolia_block_cookie">
                Find out more about Algolia Cookie Configuration in <a href="https://www.algolia.com/doc/integration/magento-2/how-it-works/analytics-overview/?client=php#algolia-cookie-configuration?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link" target="_blank">documentation</a>.
                </div>';
        $selector = '#algoliasearch_credentials_algolia_cookie_configuration';
        $method = 'after';

        $this->notices[] = [
            'selector' => $selector,
            'method' => $method,
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
        return 2;
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

    /**
     * Function created for adding the Algolia Dashboard link in the Magento recommend system configuration
     * @return void
     */
    protected function getRecommendNotice()
    {
        if (!$this->configHelper->getApplicationID()) {
            return;
        }
        $noticeContent = '<div class="algolia-perso"><br/><h2>Algolia Dashboard</h2>
        <p>Configure your Recommend models on the  <a href="https://www.algolia.com/apps/'.$this->configHelper->getApplicationID().'/recommend/models" target="_blank`">Algolia Dashboard</a></p></div>';

        $selector = '#algoliasearch_recommend_recommend';
        $method = 'after';

        $this->notices[] = [
            'selector' => $selector,
            'method' => $method,
            'message' => $noticeContent,
        ];
    }

    /**
    * Displays a warning when multiple application IDs are configured
    * @return void
    */
    protected function getMultiApplicationIDsNotice(): void
    {
        if (!$this->algoliaCredentialsManager->hasMultipleApplicationIDs()) {
            return;
        }

        $noticeTitle = 'Multi Application IDs';
        $noticeContent = '<p>You are currently using multiple Algolia application IDs with your Magento installation.</p>
        <p>When verifying data in Algolia, please make sure you are referencing the correct application.</p>';

        $this->notices[] = [
            'selector' => '.entry-edit',
            'method' => 'before',
            'message' => $this->formatNotice($noticeTitle, $noticeContent),
        ];
    }
}
