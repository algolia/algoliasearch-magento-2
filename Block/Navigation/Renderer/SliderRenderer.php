<?php

namespace Algolia\AlgoliaSearch\Block\Navigation\Renderer;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;

class SliderRenderer extends Template implements FilterRendererInterface
{
    /** @var string */
    protected $_template = 'Algolia_AlgoliaSearch::layer/filter/slider.phtml';

    /** @var string */
    protected $dataRole = 'range-slider';

    /** @var EncoderInterface */
    private $jsonEncoder;

    /** @var FormatInterface */
    protected $localeFormat;

    /**
     *
     * @param Context $context
     * @param EncoderInterface $jsonEncoder
     * @param FormatInterface $localeFormat
     * @param array $data
     */
    public function __construct(
        Context $context,
        EncoderInterface $jsonEncoder,
        FormatInterface $localeFormat,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->jsonEncoder = $jsonEncoder;
        $this->localeFormat = $localeFormat;
    }

    public function render(FilterInterface $filter)
    {
        $html  = '';
        $this->filter = $filter;

        if ($this->canRenderFilter()) {
            $this->assign('filterItems', $filter->getItems());
            $html = $this->_toHtml();
            $this->assign('filterItems', []);
        }

        return $html;
    }

    public function getFilter()
    {
        return $this->filter;
    }

    /** @return string */
    public function getJsonConfig()
    {
        $config = $this->getConfig();

        return $this->jsonEncoder->encode($config);
    }

    /** @return string */
    public function getDataRole()
    {
        $filter = $this->getFilter();

        return $this->dataRole . '-' . $filter->getRequestVar();
    }

    /**
     * {@inheritdoc}
     */
    protected function canRenderFilter()
    {
        return true;
    }

    /** @return array */
    protected function getFieldFormat()
    {
        $format = $this->localeFormat->getPriceFormat();

        $attribute = $this->getFilter()->getAttributeModel();

        $format['pattern'] = (string) $attribute->getDisplayPattern();
        $format['precision'] = (int) $attribute->getDisplayPrecision();
        $format['requiredPrecision'] = (int) $attribute->getDisplayPrecision();
        $format['integerRequired'] = (int) $attribute->getDisplayPrecision() > 0;

        return $format;
    }

    /** @return array */
    protected function getConfig()
    {
        $config = [
            'minValue' => $this->getMinValue(),
            'maxValue' => $this->getMaxValue(),
            'currentValue' => $this->getCurrentValue(),
            'fieldFormat' => $this->getFieldFormat(),
            'urlTemplate' => $this->getUrlTemplate(),
        ];

        return $config;
    }

    /** @return int */
    protected function getMinValue()
    {
        return $this->getFilter()->getMinValue();
    }

    /** @return int */
    protected function getMaxValue()
    {
        return $this->getFilter()->getMaxValue();
    }

    /** @return array */
    private function getCurrentValue()
    {
        $currentValue = $this->getFilter()->getCurrentValue();

        if (!is_array($currentValue)) {
            $currentValue = [];
        }

        if (!isset($currentValue['from']) || $currentValue['from'] === '') {
            $currentValue['from'] = $this->getMinValue();
        }

        if (!isset($currentValue['to']) || $currentValue['to'] === '') {
            $currentValue['to'] = $this->getMaxValue();
        }

        return $currentValue;
    }

    /** @return string */
    private function getUrlTemplate()
    {
        $filter = $this->getFilter();
        $item = current($this->getFilter()->getItems());

        $regexp = "/({$filter->getRequestVar()})=(-?[0-9A-Z\-\%]+)/";
        $replacement = '${1}=<%- from %>-<%- to %>';

        return preg_replace($regexp, $replacement, $item->getUrl());
    }
}
