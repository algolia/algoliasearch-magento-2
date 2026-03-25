<?php

namespace Algolia\AlgoliaSearch\Block\Widget;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class LookingSimilar extends Template implements BlockInterface
{
    /** @var ConfigHelper */
    protected $configHelper;

    protected $_template = 'recommend/widget/looking-similar.phtml';

    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        Random $mathRandom,
        array $data = []
    ) {
        $this->mathRandom = $mathRandom;
        $this->configHelper = $configHelper;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return string
     */
    public function generateUniqueToken()
    {
        return $this->mathRandom->getRandomString(5);
    }

    /**
     * @return string
     */
    public function getProductIds()
    {
        return json_encode(explode(',', (string) $this->getData('productIds')));
    }

    /**
     * @return int
     */
    public function isLookingSimilarEnabled()
    {
        return $this->configHelper->isRecommendLookingSimilarEnabled();
    }
}
