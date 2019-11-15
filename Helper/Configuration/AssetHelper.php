<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\View\Asset\Repository as AssetRepository;

class AssetHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var AssetRepository */
    private $assetRepository;

    /** @var array */
    private $videosConfig = [
        'algoliasearch_credentials' => [
            'title' => 'How to change a setting',
            'url' => 'https://www.youtube.com/watch?v=7yqOMb2SHw0',
            'thumbnail' => 'https://img.youtube.com/vi/7yqOMb2SHw0/mqdefault.jpg',
        ],
        'algoliasearch_autocomplete' => [
            'title' => 'Autocomplete menu configuration',
            'url' => 'https://www.youtube.com/watch?v=S6yuPl-bsFQ',
            'thumbnail' => 'https://img.youtube.com/vi/S6yuPl-bsFQ/mqdefault.jpg',
        ],
        'algoliasearch_instant' => [
            'title' => 'Instantsearch page configuration',
            'url' => 'https://www.youtube.com/watch?v=-gy92Pbwb64',
            'thumbnail' => 'https://img.youtube.com/vi/-gy92Pbwb64/mqdefault.jpg',
        ],
        'algoliasearch_products' => [
            'title' => 'Product search configuration',
            'url' => 'https://www.youtube.com/watch?v=6XJ11UdgVPE',
            'thumbnail' => 'https://img.youtube.com/vi/6XJ11UdgVPE/mqdefault.jpg',
        ],
        'algoliasearch_queue' => [
            'title' => 'The indexing queue',
            'url' => 'https://www.youtube.com/watch?v=0V1BSKlCm10',
            'thumbnail' => 'https://img.youtube.com/vi/0V1BSKlCm10/mqdefault.jpg',
        ],
        'algoliasearch_synonyms' => [
            'title' => 'Notable features',
            'url' => 'https://www.youtube.com/watch?v=45NKJbrs1Z4',
            'thumbnail' => 'https://img.youtube.com/vi/45NKJbrs1Z4/mqdefault.jpg',
        ],
        'algoliasearch_cc_analytics' => [
            'title' => 'Notable features',
            'url' => 'https://www.youtube.com/watch?v=45NKJbrs1Z4',
            'thumbnail' => 'https://img.youtube.com/vi/45NKJbrs1Z4/mqdefault.jpg',
        ],
    ];

    /** @var array */
    private $linksConfig = [
        'algoliasearch_credentials' => [
            [
                'title' => 'Documentation',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/getting-started/quick-start/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'FAQ',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/troubleshooting/general-faq/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconFaq',
            ],
            [
                'title' => 'Issues',
                'url' => 'https://github.com/algolia/algoliasearch-magento-2/issues/',
                'icon' => 'iconIssues',
            ],
        ],
        'algoliasearch_autocomplete' => [
            [
                'title' => 'Customize autocomplete',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/customize/autocomplete-menu/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'Add an external data source',
                'url' => 'https://www.algolia.com/doc/integration/magento-1/guides/adding-autocomplete-source/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'Use backend events',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/customize/custom-back-end-events/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_instant' => [
            [
                'title' => 'Customize InstantSearch',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/customize/instant-search-page/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'Use backend events',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/customize/custom-back-end-events/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_products' => [
            [
                'title' => 'Products\' indexing documentation',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#products-indexing',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_categories' => [
            [
                'title' => 'Categories\' indexing documentation',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#categories-indexing',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_images' => [
            [
                'title' => 'Issues with images',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/troubleshooting/general-faq/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#why-are-images-not-showing-up',
                'icon' => 'iconIssues',
            ],
        ],
        'algoliasearch_queue' => [
            [
                'title' => 'Indexing queue documentation',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#general-information',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'Indexing troubleshooting guide',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/troubleshooting/data-indexes-queues/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#general-information',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_synonyms' => [
            [
                'title' => 'Synonyms documentation',
                'url' => 'https://www.algolia.com/doc/guides/managing-results/optimize-search-results/adding-synonyms/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_cc_analytics' => [
            [
                'title' => 'Click & Conversion Analytics',
                'url' => 'https://www.algolia.com/doc/guides/getting-insights-and-analytics/click-analytics/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
            [
                'title' => 'Documentation for magento2',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/how-it-works/click-and-conversion-analytics/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_analytics' => [
            [
                'title' => 'Documentation of Magento Google analytics',
                'url' => 'https://www.algolia.com/doc/integration/magento-2/how-it-works/google-analytics/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link',
                'icon' => 'iconDocs',
            ],
        ],
        'algoliasearch_extra_settings' => [
            [
                'title' => 'List of possible settings',
                'url' => 'https://www.algolia.com/doc/api-reference/settings-api-parameters/#index-settings-parameters',
                'icon' => 'iconDocs',
            ],
        ],
    ];

    private $icons = [];

    /** @var array */
    private $videoInstallation = [
        'title' => 'Installation & Setup',
        'url' => 'https://www.youtube.com/watch?v=twEj_VBWxp8',
        'thumbnail' => 'https://img.youtube.com/vi/twEj_VBWxp8/mqdefault.jpg',
    ];

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ConfigHelper $configHelper,
        AssetRepository $assetRepository
    ) {
        $this->configHelper = $configHelper;
        $this->assetRepository = $assetRepository;

        $this->icons = [
            'iconDocs' => $this->assetRepository->getUrl('Algolia_AlgoliaSearch::images/icon-docs.svg'),
            'iconFaq' => $this->assetRepository->getUrl('Algolia_AlgoliaSearch::images/icon-faq.svg'),
            'iconIssues' => $this->assetRepository->getUrl('Algolia_AlgoliaSearch::images/icon-issues.svg'),
        ];
        parent::__construct($context);
    }

    /** @return array|void */
    protected function getVideoConfig($section, $configNotSet)
    {
        $config = null;

        if (isset($this->videosConfig[$section])) {
            $config = $this->videosConfig[$section];
        }

        // If the credentials are not set, display the installation video
        if ($configNotSet) {
            $config = $this->videoInstallation;
        }

        return $config;
    }

    /** @return array|void */
    protected function getLinksConfig($section)
    {
        $config = null;

        if (isset($this->linksConfig[$section])) {
            $config = $this->linksConfig[$section];
        }

        return $config;
    }

    public function getLinksAndVideoTemplate($section)
    {
        $configNotSet = false;
        // Check if all the mandatory credentials have been set
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $configNotSet = true;
        }

        $linksTemplate = $this->getLinksTemplate($section);
        $videoTemplate = $this->getVideoTemplate($section, $configNotSet);
        $template  = '<div class="algolia-admin-content-wrapper">';
        $template .= $linksTemplate;
        $template .= $videoTemplate;
        $template .= '</div><br><hr><br>';

        return $template;
    }

    protected function getVideoTemplate($section, $configNotSet)
    {
        $config = $this->getVideoConfig($section, $configNotSet);
        $template = '';

        if (is_array($config)) {
            $template = '<div class="algolia-admin-content-videos">
                            <p>
                                <span>Related video:</span>
                                <a target="_blank" href="' . $config['url'] . '"><img src="' . $config['thumbnail'] . '"/></a>
                                <a target="_blank" href="' . $config['url'] . '">' . $config['title'] . '</a>
                            </p>
                        </div>';
        }

        return $template;
    }

    protected function getLinksTemplate($section)
    {
        $config = $this->getLinksConfig($section);
        $template = '';

        if (is_array($config)) {
            $links = '';
            $i = 0;
            foreach ($config as $link) {
                $links .= '<span><img src="' . $this->icons[$link['icon']] . '"/><a target="_blank" href="' . $link['url'] . '">' . $link['title'] . '</a></span>';
                if ($i%2 == 1) {
                    $links .= '<br/>';
                }
                $i++;
            }

            $template = '<div class="algolia-admin-content-links"><p>' . $links . '</p></div>';
        }

        return $template;
    }
}
