<?php

namespace Algolia\AlgoliaSearch\Block\System\Form\Field;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Logo extends \Magento\Config\Block\System\Config\Form\Field
{
    private $proxyHelper;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ProxyHelper $proxyHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->proxyHelper = $proxyHelper;
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->showLogo()) {
            $element->setDisabled(true);
            $element->setValue(0);

            $comment = __('Do you want to remove Algolia logo from autocomplete menu?')
                . '<br><span class="algolia-config-warning">&#9888;</span>'
                . __('Only paid customers are allowed to remove the logo.');

            $element->setComment($comment);
        }

        return parent::_getElementHtml($element);
    }

    /**
     * @return bool
     */
    public function showLogo()
    {
        $info = $this->proxyHelper->getClientConfigurationData();

        return isset($info['require_logo']) && $info['require_logo'] == 1;
    }
}
