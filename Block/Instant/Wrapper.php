<?php

namespace Algolia\AlgoliaSearch\Block\Instant;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\View\Element\Template;

class Wrapper extends Template
{
    private $config;

    public function __construct(
        Template\Context $context,
        ConfigHelper $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    public function hasFacets()
    {
        return count($this->config->getFacets()) > 0;
    }
}
