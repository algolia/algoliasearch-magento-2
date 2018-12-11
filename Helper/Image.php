<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;

class Image extends \Magento\Catalog\Helper\Image
{
    private $logger;
    private $options;

    /**
     * Image constructor.
     *
     * @param Context $context
     * @param ImageFactory $productImageFactory
     * @param Repository $assetRepo
     * @param ConfigInterface $viewConfig
     * @param Logger $logger
     * @param array $options
     */
    public function __construct(
        Context $context,
        ImageFactory $productImageFactory,
        Repository $assetRepo,
        ConfigInterface $viewConfig,
        Logger $logger,
        $options = []
    ) {
        parent::__construct($context, $productImageFactory, $assetRepo, $viewConfig);
        $this->logger = $logger;

        $this->options = array_merge([
            'shouldRemovePubDir' => false,
        ], $options);
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

        if ($this->options['shouldRemovePubDir']) {
            $url = $this->removePubDirectory($url);
        }

        return $url;
    }

    protected function initBaseFile()
    {
        $model = $this->_getModel();
        $baseFile = $model->getBaseFile();
        if (!$baseFile) {
            if ($this->getImageFile()) {
                $model->setBaseFile($this->getImageFile());
            } else {
                $model->setBaseFile($this->getProduct()->getImage());
            }
        }

        return $this;
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
