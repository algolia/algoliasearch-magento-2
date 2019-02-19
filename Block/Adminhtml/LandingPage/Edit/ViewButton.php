<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\LandingPage\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ViewButton extends AbstractButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        if ($this->getObjectUrlKey()) {
            return [
                'label' => __('View'),
                'class' => 'view',
                'on_click' => sprintf("window.open('%s','_blank');", $this->getLandingPageUrl()),
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
    public function getLandingPageUrl($scope = null, $store = null)
    {
        if ($this->getObject()->getStoreId() != 0) {
            $this->frontendUrlBuilder->setScope($this->getObject()->getStoreId());
        }

        $href = $this->frontendUrlBuilder->getUrl($this->getObjectUrlKey());

        return $href;
    }
}
