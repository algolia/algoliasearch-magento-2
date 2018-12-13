<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Support;

use Algolia\AlgoliaSearch\Block\Adminhtml\Support\Components\LegacyVersion;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

abstract class AbstractSupportTemplate extends Template
{
    public function __construct(Context $context, $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getLegacyVersionHtml()
    {
        return $this
            ->getLayout()
            ->createBlock(LegacyVersion::class)
            ->toHtml();
    }
}
