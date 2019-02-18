<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

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

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;

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
                $item[$this->getData('name')] = [
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
