<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ViewButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        if ($this->getObjectQueryText()) {
            return [
                'label' => __('View'),
                'class' => 'view',
                'on_click' => sprintf("window.open('%s','_blank');", $this->getQueryTextViewUrl()),
                'sort_order' => 50,
            ];
        }
    }

    /**
     * Get action url
     *
     * @param string $scope
     * @param string $store
     *
     * @return string
     */
    public function getQueryTextViewUrl($scope = null, $store = null)
    {
        if ($this->getObject()->getStoreId() != 0) {
            $this->frontendUrlBuilder->setScope($this->getObject()->getStoreId());
        }

        $href = $this->frontendUrlBuilder->getUrl('catalogsearch/result/?q=' . $this->getObjectQueryText());
        $href = rtrim($href, '/');

        return $href;
    }
}
