<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Model\LandingPage as LandingPageModel;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\CatalogSearch\Block\Result;
use Magento\CatalogSearch\Helper\Data;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Search\Model\QueryFactory;

/**
 * @method int getPageId()
 */
class LandingPage extends Result
{
    /** @var FilterProvider */
    protected $filterProvider;

    /** @var LandingPageModel */
    protected $landingPage;

    /** @var LandingPageFactory */
    protected $landingPageFactory;

    /**
     * Construct
     *
     * @param Magento\Framework\View\Element\Template\Context $context
     * @param LayerResolver $layerResolver
     * @param Data $catalogSearchData
     * @param QueryFactory $queryFactory
     * @param FilterProvider $filterProvider
     * @param LandingPageModel $landingPage
     * @param LandingPageFactory $landingPageFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        LayerResolver $layerResolver,
        Data $catalogSearchData,
        QueryFactory $queryFactory,
        FilterProvider $filterProvider,
        LandingPageModel $landingPage,
        LandingPageFactory $landingPageFactory,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $layerResolver,
            $catalogSearchData,
            $queryFactory,
            $data
        );

        $this->filterProvider = $filterProvider;
        $this->landingPage = $landingPage;
        $this->landingPageFactory = $landingPageFactory;
    }

    /**
     * Retrieve Page instance
     *
     * @return LandingPageModel
     */
    public function getPage()
    {
        if (!$this->hasData('page')) {
            if ($this->getPageId()) {
                /** @var LandingPageModel $page */
                $page = $this->landingPageFactory->create();
                $page->setStoreId($this->_storeManager->getStore()->getId())->load($this->getPageId(), 'url_key');
            } else {
                $page = $this->landingPage;
            }
            $this->setData('page', $page);
        }

        return $this->getData('page');
    }

    /**
     * Prepare global layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $page = $this->getPage();
        $this->pageConfig->addBodyClass('algolia-landingpage-' . $page->getUrlKey());
        $metaTitle = $page->getMetaTitle();
        $this->pageConfig->getTitle()->set($page->getTitle() ? $page->getTitle() : $metaTitle);
        $this->pageConfig->setKeywords($page->getMetaKeywords());
        $this->pageConfig->setDescription($page->getMetaDescription());

        $this->getLayout()->getBlock('landing_page_content')->setText($this->getLandingPageContent());
        $this->getLayout()->getBlock('landing_page_custom_js')->setText($this->getLandingCustomJs());
        $this->getLayout()->getBlock('landing_page_custom_css')->setText($this->getLandingCustomCss());

        return $this;
    }

    protected function getLandingPageContent()
    {
        return $this->filterProvider->getPageFilter()->filter($this->getPage()->getContent());
    }

    protected function getLandingCustomJs()
    {
        $customJs = $this->getPage()->getCustomJs();

        if (!$customJs) {
            return '';
        }

        return '<script type="text/javascript">' . $customJs . '</script>';
    }

    protected function getLandingCustomCss()
    {
        $customCss = $this->getPage()->getCustomCss();

        if (!$customCss) {
            return '';
        }

        return '<style type="text/css">' . $customCss . '</style>';
    }

    /**
     * Return identifiers for produced content
     *
     * @return array
     */
    public function getIdentities()
    {
        return [\Algolia\AlgoliaSearch\Model\LandingPage::CACHE_TAG . '_' . $this->getPage()->getId()];
    }
}
