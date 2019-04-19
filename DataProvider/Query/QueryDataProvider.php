<?php

namespace Algolia\AlgoliaSearch\DataProvider\Query;

use Algolia\AlgoliaSearch\Model\ResourceModel\Query\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Store\Model\StoreManagerInterface;

class QueryDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * Constructor
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param StoreManagerInterface $storeManager
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->storeManager = $storeManager;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        $baseurl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        foreach ($items as $query) {
            $temp = $query->getData();
            if ($temp['banner_image']) {
                $img = [];
                $img[0]['image'] = $temp['banner_image'];
                $img[0]['url'] = $baseurl . 'algolia_img/' . $temp['banner_image'];
                $temp['banner_image'] = $img;
            }

            $this->loadedData[$query->getId()] = $temp;
        }

        $data = $this->dataPersistor->get('query');

        if (!empty($data)) {
            $query = $this->collection->getNewEmptyItem();
            $query->setData($data);
            $this->loadedData[$query->getId()] = $query->getData();
            $this->dataPersistor->clear('query');
        }

        return $this->loadedData;
    }
}
