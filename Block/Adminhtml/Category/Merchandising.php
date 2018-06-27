<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Category;

use Algolia\AlgoliaSearch\Block\Adminhtml\Category\Tab\Merchandising as MerchandisingTab;

class Merchandising extends \Magento\Backend\Block\Template
{
    /** @var string */
    protected $_template = 'catalog/category/edit/merchandising.phtml';

    /** @var MerchandisingTab */
    protected $blockGrid;

    /** @var \Magento\Framework\Registry */
    protected $registry;

    /** @var \Magento\Framework\Json\EncoderInterface */
    protected $jsonEncoder;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->jsonEncoder = $jsonEncoder;
        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getBlockGrid()
    {
        if (null === $this->blockGrid) {
            $this->blockGrid = $this->getLayout()->createBlock(
                MerchandisingTab::class,
                'category.algolia_merchandising.grid'
            );
        }

        return $this->blockGrid;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getGridHtml()
    {
        return $this->getBlockGrid()->toHtml();
    }

    /** @return string */
    public function getProductsJson()
    {
        $products = $this->getCategory()->getProductsPosition();
        if (!empty($products)) {
            return $this->jsonEncoder->encode($products);
        }

        return '{}';
    }

    /** @return \Magento\Catalog\Model\Category | null */
    public function getCategory()
    {
        return $this->registry->registry('category');
    }
}
