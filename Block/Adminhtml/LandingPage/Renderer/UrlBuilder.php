<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Renderer;

/**
 * Url builder class used to compose dynamic urls.
 */
class UrlBuilder
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $frontendUrlBuilder;

    /**
     * @param \Magento\Framework\UrlInterface $frontendUrlBuilder
     */
    public function __construct(\Magento\Framework\UrlInterface $frontendUrlBuilder)
    {
        $this->frontendUrlBuilder = $frontendUrlBuilder;
    }

    /**
     * Set store id
     *
     * @param int $storeId

     *
     * @return void
     */
    public function setScope($storeId)
    {
        $this->frontendUrlBuilder->setScope($storeId);
    }

    /**
     * Get action url
     *
     * @param string $routePath
     * @param string $scope
     * @param string $store
     *
     * @return string
     */
    public function getUrl($routePath)
    {
        $href = $this->frontendUrlBuilder->getUrl(
            $routePath,
            [
                '_current' => false,
                '_nosid' => true,
            ]
        );

        return $href;
    }
}
