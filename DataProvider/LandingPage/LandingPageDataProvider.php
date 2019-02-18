<?php

namespace Algolia\AlgoliaSearch\DataProvider\LandingPage;

use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;

class LandingPageDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

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
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        foreach ($items as $landingPage) {
            $this->loadedData[$landingPage->getId()] = $landingPage->getData();
        }

        $data = $this->dataPersistor->get('landing_page');

        if (!empty($data)) {
            $landingPage = $this->collection->getNewEmptyItem();
            $landingPage->setData($data);
            $this->loadedData[$landingPage->getId()] = $landingPage->getData();
            $this->dataPersistor->clear('landing_page');
        }

        return $this->loadedData;
    }
}
