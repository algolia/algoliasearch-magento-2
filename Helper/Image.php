<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;

class Image extends \Magento\Catalog\Helper\Image
{
    /**
     * @var ConfigHelper
     */
    public $configHelper;
    private $logger;

    /**
     * Image constructor.
     *
     * @param Context $context
     * @param ImageFactory $productImageFactory
     * @param Repository $assetRepo
     * @param ConfigInterface $viewConfig
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context $context,
        ImageFactory $productImageFactory,
        Repository $assetRepo,
        ConfigInterface $viewConfig,
        Logger $logger,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context, $productImageFactory, $assetRepo, $viewConfig);
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    public function getUrl()
    {
        try {
            $this->applyScheduledActions();

            $url = $this->_getModel()->getUrl();
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage());
            $this->logger->log($e->getTraceAsString());

            $url = $this->getDefaultPlaceholderUrl();
        }

        $url = $this->removeProtocol($url);
        $url = $this->removeDoubleSlashes($url);

        if ($this->configHelper->shouldRemovePubDirectory()) {
            $url = $this->removePubDirectory($url);
        }

        return $url;
    }

    public function removeProtocol($url)
    {
        return str_replace(['https://', 'http://'], '//', $url);
    }

    public function removeDoubleSlashes($url)
    {
        $url = str_replace('//', '/', $url);
        $url = '/' . $url;

        return $url;
    }

    public function removePubDirectory($url)
    {
        return str_replace('/pub/', '/', $url);
    }
}
