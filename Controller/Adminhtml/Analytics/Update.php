<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

class Update extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
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

        $response->setData(['html_content' => $block]);

        return $this->resultJsonFactory->create()->setJsonData($response->toJson());
    }
}
