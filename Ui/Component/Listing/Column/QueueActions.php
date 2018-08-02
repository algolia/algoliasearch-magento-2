<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\Escaper;

/**
 * Class QueueActions
 */
class QueueActions extends Column
{
    /**
     * Url path
     */
    const URL_PATH_VIEW = 'algolia_algoliasearch/queue/view';

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = [
//                    'delete' => [
//                        'href' => $this->urlBuilder->getUrl(
//                            static::URL_PATH_DELETE,
//                            [
//                                'id' => $item['job_id']
//                            ]
//                        ),
//                        'label' => __('Delete'),
//                        'confirm' => [
//                            'title' => __('Delete ?'),
//                            'message' => __('Are you sure you want to delete this job ?')
//                        ]
//                    ],
//                    'execute' => [
//                        'href' => $this->urlBuilder->getUrl(
//                            static::URL_PATH_EXECUTE,
//                            [
//                                'id' => $item['job_id']
//                            ]
//                        ),
//                        'label' => __('Execute'),
//                        'confirm' => [
//                            'title' => __('Execute ?'),
//                            'message' => __('Are you sure you want to execute this job ?')
//                        ]
//                    ],
                    'view' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_VIEW,
                            [
                                'id' => $item['job_id']
                            ]
                        ),
                        'label' => __('View')
                    ]
                ];
            }
        }

        return $dataSource;
    }
}
