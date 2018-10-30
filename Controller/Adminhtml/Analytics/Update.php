<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

class Update extends AbstractAction
{
    /**
     * Return AJAX Overview Content Section.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $response = $this->_objectManager->create(\Magento\Framework\DataObject::class);
        $response->setError(false);

        $this->_getSession()->setAlgoliaAnalyticsFormData($this->getRequest()->getParams());

        $layout = $this->layoutFactory->create();
        $block = $layout->createBlock('\Algolia\AlgoliaSearch\Block\Adminhtml\Analytics\Index')
            ->setTemplate('Algolia_AlgoliaSearch::analytics/overview.phtml')
            ->toHtml();

        $response->setData(array('html_content' => $block));

        return $this->resultJsonFactory->create()->setJsonData($response->toJson());
    }
}
