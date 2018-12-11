<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

class Update extends AbstractAction
{
    public function execute()
    {
        $response = $this->_objectManager->create(\Magento\Framework\DataObject::class);
        $response->setError(false);

        $this->_getSession()->setAlgoliaAnalyticsFormData($this->getRequest()->getParams());

        $layout = $this->layoutFactory->create();

        $block = $layout
            ->createBlock(\Magento\Backend\Block\Template::class)
            ->setData('view_model', $this->_objectManager->create(\Algolia\AlgoliaSearch\ViewModel\Adminhtml\Analytics\Index::class))
            ->setTemplate('Algolia_AlgoliaSearch::analytics/overview.phtml')
            ->toHtml();

        $response->setData(['html_content' => $block]);

        return $this->resultJsonFactory->create()->setJsonData($response->toJson());
    }
}
