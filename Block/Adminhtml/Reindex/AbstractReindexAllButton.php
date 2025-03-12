<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

abstract class AbstractReindexAllButton implements ButtonProviderInterface
{
    protected string $entity;

    protected string $redirectPath;

    public function __construct(
        protected Context $context,
        protected ConfigHelper $configHelper
    ) {}

    /**
     * @return string
     */
    protected function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    protected function getRedirectPath(): string
    {
        return $this->redirectPath;
    }

    /**
     * @return array
     */
    public function getButtonData(): array
    {
        $entity = $this->getEntity();
        $redirectPath = $this->getRedirectPath();

        $message = "Are you sure you want to reindex all $entity to Algolia ?";

        if (!$this->configHelper->isQueueActive() && $entity === 'products') {
            $message .= ' Warning : Your Indexing Queue is not activated. Depending on the size of the data you want to index, it may takes a lot of time and resources.';
            $message .= 'We highly suggest to turn it on if you\'re performing a full product reindexing with a large catalog.';
        }

        $message = htmlentities(__($message));
        $url = $this->context->getUrlBuilder()->getUrl('algolia_algoliasearch/indexingmanager/reindex');

        return [
            'label'      => __('Reindex All items to Algolia'),
            'class'      => 'algolia_reindex_all',
            'on_click'   => "deleteConfirm('{$message}', '{$url}', {data:{'entity':'{$entity}', 'redirect': '{$redirectPath}'}})",
            'sort_order' => 5,
        ];
    }
}
