<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The purpose of this class is to render different cached versions of the pages according to the user agent.
 * If the "prevent backend rendering" configuration is turned on, we don't want to render the results on the backend
 * side only for humans but we want to do it for the robots (configured in the "advanced" section of the extension).
 * So with this plugin, two versions of the pages are cached : one for humans, and one for robots.
 */
class RenderingCacheContextPlugin
{
    public const RENDERING_CONTEXT = 'algolia_rendering_context';
    public const RENDERING_WITH_BACKEND = 'with_backend';
    public const RENDERING_WITHOUT_BACKEND = 'without_backend';

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
     * Add a rendering context to the vary string to distinguish how which versions of the category PLP should be cached
     * (If the "prevent backend rendering" configuration is enabled and the user agent is not whitelisted to display it,
     * we set a different page variation, and the FPC stores a different cached page)
     *
     * @param HttpContext $subject
     *
     * @return array original params
     * @throws NoSuchEntityException
     */
    public function beforeGetVaryString(HttpContext $subject): array {
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->request->getControllerName() != 'category' || !$this->configHelper->replaceCategories($storeId)) {
            return [];
        }

        $context = $this->configHelper->preventBackendRendering() ?
            self::RENDERING_WITHOUT_BACKEND :
            self::RENDERING_WITH_BACKEND;

        $subject->setValue(
            self::RENDERING_CONTEXT,
            $context,
            $context
        );

        return [];
    }
}
