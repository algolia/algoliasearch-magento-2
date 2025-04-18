<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ProductTypeConfigurable;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;

class Image extends \Magento\Catalog\Helper\Image
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    private $logger;

    /**
     * Image constructor.
     *
     * @param Context $context
     * @param ImageFactory $productImageFactory
     * @param Repository $assetRepo
     * @param ConfigInterface $viewConfig
     * @param DiagnosticsLogger $logger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context           $context,
        ImageFactory      $productImageFactory,
        Repository        $assetRepo,
        ConfigInterface   $viewConfig,
        DiagnosticsLogger $logger,
        ConfigHelper      $configHelper
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

        if ($this->configHelper->shouldRemovePubDirectory()) {
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
                $model->setBaseFile($this->getProductImage($model));
            }
        }

        return $this;
    }

    /**
     * Configurable::setImageFromChildProduct() only pulls 'image' type
     * and not the type set by the imageHelper
     *
     * @param \Magento\Catalog\Model\Product\Image $model
     *
     * @return mixed|string|null
     */
    private function getProductImage(\Magento\Catalog\Model\Product\Image $model)
    {
        $imageUrl = $this->getProduct()->getData($model->getDestinationSubdir());
        if (($imageUrl === null || $imageUrl == '' || $imageUrl == 'no_selection') &&
            $this->getProduct()->getTypeId() == ProductTypeConfigurable::TYPE_CODE) {
            $imageUrl = $this->getType() !== 'image' && $this->getConfigurableProductImage() ?
                $this->getConfigurableProductImage() : $this->getProduct()->getImage();
        }

        return $imageUrl;
    }

    private function getConfigurableProductImage()
    {
        $childProducts = $this->getProduct()->getTypeInstance()->getUsedProducts($this->getProduct());
        foreach ($childProducts as $childProduct) {
            $childImageUrl = $childProduct->getData($this->getType());
            if ($childImageUrl && $childImageUrl !== 'no_selection') {
                return $childImageUrl;
            }
        }

        return null;
    }

    public function removePubDirectory($url)
    {
        return str_replace('/pub/', '/', $url);
    }
}
