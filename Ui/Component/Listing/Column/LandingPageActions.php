<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Renderer\UrlBuilder;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class LandingPageActions extends Column
{
    const URL_PATH_EDIT = 'algolia_algoliasearch/landingpage/edit';
    const URL_PATH_DELETE = 'algolia_algoliasearch/landingpage/delete';
    const URL_PATH_DUPLICATE = 'algolia_algoliasearch/landingpage/duplicate';

    /** @var UrlInterface */
    protected $urlBuilder;

    /** @var Escaper */
    protected $escaper;

    /** @var UrlBuilder */
    protected $frontendUrlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param UrlBuilder $frontendUrlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Escaper $escaper,
        UrlBuilder $frontendUrlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;
        $this->frontendUrlBuilder = $frontendUrlBuilder;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $title = $this->escaper->escapeHtml($item['title']);
                if (isset($item['store_id_num']) && $item['store_id_num'] != 0) {
                    $this->frontendUrlBuilder->setScope($item['store_id_num']);
                }
                $item[$this->getData('name')] = [
                    'view' => [
                        'href' => $this->frontendUrlBuilder->getUrl($item['url_key']),
                        'label' => __('View'),
                        'target' => '_blank',
                    ],
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_EDIT,
                            [
                                'id' => $item['landing_page_id'],
                            ]
                        ),
                        'label' => __('Edit'),
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_DELETE,
                            [
                                'id' => $item['landing_page_id'],
                            ]
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete "%1"', $title),
                            'message' => __('Are you sure you want to delete "%1"?', $title),
                        ],
                    ],
                    'duplicate' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_DUPLICATE,
                            [
                                'id' => $item['landing_page_id'],
                            ]
                        ),
                        'label' => __('Duplicate'),
                        'confirm' => [
                            'title' => __('Duplicate "%1"', $title),
                            'message' => __('Are you sure you want to duplicate "%1"?', $title),
                        ],
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
