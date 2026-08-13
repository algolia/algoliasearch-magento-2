<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query\Edit;

use Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Renderer\UrlBuilder;
use Algolia\AlgoliaSearch\Model\QueryFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Query as QueryResource;
use Magento\Backend\Block\Widget\Context;

abstract class AbstractButton
{
    public function __construct(
        protected Context       $context,
        protected QueryFactory  $queryFactory,
        protected UrlBuilder    $frontendUrlBuilder,
        protected QueryResource $queryResource
    ) {
    }

    /**
     * Return object
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return int|null
     */
    public function getObject()
    {
        try {
            $modelId = $this->context->getRequest()->getParam('id');
            /** @var \Algolia\AlgoliaSearch\Model\Query $query */
            $query = $this->queryFactory->create();
            $this->queryResource->load($query, $modelId);

            return $query;
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
        }

        return null;
    }

    /**
     * Return object ID
     *
     * @return int|null
     */
    public function getObjectId()
    {
        return $this->getObject() ? $this->getObject()->getId() : null;
    }

    /**
     * Return object query text
     *
     * @return string|null
     */
    public function getObjectQueryText()
    {
        return $this->getObject() ? $this->getObject()->getQueryText() : null;
    }

    /**
     * Generate url by route and parameters
     *
     * @param string $route
     * @param array $params
     *
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    /**
     * get the button data
     *
     * @return array
     */
    abstract public function getButtonData();
}
