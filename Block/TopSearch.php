<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Data\CollectionDataSourceInterface;
use Magento\Framework\View\Element\Template;
use Magento\Search\Helper\Data as CatalogSearchHelper;

class TopSearch extends Template implements CollectionDataSourceInterface
{
    private $config;
    private $catalogSearchHelper;

    public function __construct(
        Template\Context $context,
        ConfigHelper $config,
        CatalogSearchHelper $catalogSearchHelper,
        array $data = []
    ) {
        $this->config = $config;
        $this->catalogSearchHelper = $catalogSearchHelper;

        parent::__construct($context, $data);
    }

    public function isDefaultSelector()
    {
        return $this->config->isDefaultSelector();
    }

    public function getResultUrl()
    {
        return $this->catalogSearchHelper->getResultUrl();
    }

    public function getQueryParamName()
    {
        return $this->catalogSearchHelper->getQueryParamName();
    }
}
