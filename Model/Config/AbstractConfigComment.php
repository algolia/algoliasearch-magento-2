<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

abstract class AbstractConfigComment implements CommentInterface
{
    public function __construct(
        protected RequestInterface $request,
        protected UrlInterface     $urlInterface
    ) {}

    protected function getConfigLink(string $section, string $fragment = "", bool $scoped = false): string
    {
        $url = $this->urlInterface->getUrl("adminhtml/system_config/edit/section/$section", $scoped ? $this->getScopeParams() : []);
        if ($fragment) {
            $url .= "#$fragment";
        }
        return $url;
    }

    protected function getScopeParams(): array
    {
        $params = [];
        if ($website = $this->request->getParam('website')) {
            $params['website'] = $website;
        }
        elseif ($store = $this->request->getParam('store')) {
            $params['store'] = $store;
        }
        return $params;
    }

}
