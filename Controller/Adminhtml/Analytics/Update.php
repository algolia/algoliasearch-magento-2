<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

use Algolia\AlgoliaSearch\ViewModel\Adminhtml\Analytics\Overview;
use Magento\Backend\Block\Template;
use Magento\Framework\DataObject;

class Update extends AbstractAction
{
    public function execute()
    {
        $response = $this->_objectManager->create(DataObject::class);
        $response->setError(false);

        $this->_getSession()->setAlgoliaAnalyticsFormData($this->getRequest()->getParams());

        $layout = $this->layoutFactory->create();

        $block = $layout
            ->createBlock(Template::class)
            ->setData('view_model', $this->_objectManager->create(Overview::class))
            ->setTemplate('Algolia_AlgoliaSearch::analytics/overview.phtml')
            ->toHtml();

        $response->setData(['html_content' => $block]);

        return $this->resultJsonFactory->create()->setJsonData($response->toJson());
    }
}
