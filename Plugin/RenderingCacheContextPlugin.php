<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;

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

    public function __construct(
        protected ConfigHelper $configHelper,
        protected StoreManagerInterface $storeManager,
        protected Http $request,
        protected UrlFinderInterface $urlFinder
    ) { }

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
        if (!$this->shouldApplyCacheContext()) {
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

    /**
     * @param int $storeId
     * @return string
     */
    protected function getOriginalRoute(int $storeId): string
    {
        $requestUri = $this->request->getRequestUri();

        $rewrite = $this->urlFinder->findOneByData([
            'request_path' => ltrim($requestUri, '/'),
            'store_id'     => $storeId,
        ]);

        return $rewrite?->getTargetPath() ?? "";
    }

    /**
     * @param int $storeId
     * @return bool
     */
    protected function isCategoryPage(int $storeId): bool
    {
        $controller = $this->request->getControllerName();
        return $controller === 'category' || str_starts_with($this->getOriginalRoute($storeId), 'catalog/category');
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function shouldApplyCacheContext(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        return $this->isCategoryPage($storeId) && $this->configHelper->replaceCategories($storeId);
    }
}
