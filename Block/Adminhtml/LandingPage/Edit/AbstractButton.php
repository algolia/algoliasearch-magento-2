<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Edit;

use Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Renderer\UrlBuilder;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Backend\Block\Widget\Context;

abstract class AbstractButton
{
    /** @var Context */
    protected $context;

    /** @var LandingPageFactory */
    protected $landingPageFactory;

    /** @var UrlBuilder */
    protected $frontendUrlBuilder;

    /**
     * PHP Constructor
     *
     * @param Context $context
     * @param LandingPageFactory $landingPageFactory
     * @param UrlBuilder $frontendUrlBuilder
     *
     * @return AbstractButton
     */
    public function __construct(
        Context $context,
        LandingPageFactory $landingPageFactory,
        UrlBuilder $frontendUrlBuilder
    ) {
        $this->context = $context;
        $this->landingPageFactory = $landingPageFactory;
        $this->frontendUrlBuilder = $frontendUrlBuilder;
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
            /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
            $landingPage = $this->landingPageFactory->create();
            $landingPage->getResource()->load($landingPage, $modelId);

            return $landingPage;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
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
     * Return object ID
     *
     * @return int|null
     */
    public function getObjectUrlKey()
    {
        return $this->getObject() ? $this->getObject()->getUrlKey() : null;
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
