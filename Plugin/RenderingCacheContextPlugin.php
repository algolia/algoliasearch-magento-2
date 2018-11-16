<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\StoreManagerInterface;

class RenderingCacheContextPlugin
{
    const RENDERING_CONTEXT = 'rendering_context';
    const RENDERING_WITH_BACKEND = 'with_backend';
    const RENDERING_WITHOUT_BACKEND = 'without_backend';

    private $configHelper;
    private $storeManager;
    private $request;

    public function __construct(
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        Http $request
    ) {
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * Add Rendering context for caching purposes
     * (If the prevent rendering configuration is enabled and the user agent has no white card to display it,
     * we set a different page variation, and the FPC stores a different cached page)
     *
     * @param HttpContext $subject
     *
     * @return array
     */
    public function beforeGetVaryString(HttpContext $subject)
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (! ($this->request->getControllerName() === 'category'
                && $this->configHelper->replaceCategories($storeId) === true)) {
            return [];
        }

        $context = $this->configHelper->preventBackendRendering() ?
            self::RENDERING_WITHOUT_BACKEND :
            self::RENDERING_WITH_BACKEND;

        $subject->setValue(self::RENDERING_CONTEXT, $context, self::RENDERING_WITH_BACKEND);

        return [];
    }
}
