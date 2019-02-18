<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Save extends AbstractAction
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * PHP Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param LandingPageFactory $landingPageFactory
     * @param MerchandisingHelper $merchandisingHelper
     * @param ProxyHelper $proxyHelper
     * @param StoreManagerInterface $storeManager
     * @param DataPersistorInterface $dataPersistor
     *
     * @return Save
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        LandingPageFactory $landingPageFactory,
        MerchandisingHelper $merchandisingHelper,
        ProxyHelper $proxyHelper,
        StoreManagerInterface $storeManager,
        DataPersistorInterface $dataPersistor
    ) {
        $this->dataPersistor = $dataPersistor;

        parent::__construct(
            $context,
            $coreRegistry,
            $landingPageFactory,
            $merchandisingHelper,
            $proxyHelper,
            $storeManager
        );
    }

    /**
     * Execute the action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $data = $this->getRequest()->getPostValue();

        if (!empty($data)) {
            if (empty($data['landing_page_id'])) {
                $data['landing_page_id'] = null;
            }
            $landingPageId = $data['landing_page_id'];

            /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
            $landingPage = $this->landingPageFactory->create();

            if ($landingPageId) {
                $landingPage->getResource()->load($landingPage, $landingPageId);

                if (!$landingPage->getId()) {
                    $this->messageManager->addErrorMessage(__('This landing page does not exist.'));

                    return $resultRedirect->setPath('*/*/');
                }
            }

            if (isset($data['algolia_query']) && $data['algolia_query'] != $data['query']) {
                $data['query'] = $data['algolia_query'];
            }

            if (isset($data['algolia_configuration']) && $data['algolia_configuration'] != $data['configuration']) {
                $data['configuration'] = $data['algolia_configuration'];
            }

            $landingPage->setData($data);

            try {
                $landingPage->getResource()->save($landingPage);

                if (isset($data['algolia_merchandising_positions']) && $data['algolia_merchandising_positions'] != '') {
                    $this->manageQueryRules($landingPage->getId(), $data);
                }

                $this->messageManager->addSuccessMessage(__('The landing page has been saved.'));
                $this->dataPersistor->clear('algolia_algoliasearch_landing_page');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $landingPage->getId()]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the landing page. %1', $e->getMessage())
                );
            }

            $this->dataPersistor->set('landing_page', $data);

            return $resultRedirect->setPath('*/*/edit', ['id' => $landingPageId]);
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function manageQueryRules($landingPageId, $data)
    {
        $positions = json_decode($data['algolia_merchandising_positions'], true);
        $stores = [];
        if ($data['store_id'] == 0) {
            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[] = $store->getId();
                }
            }
        } else {
            $stores[] = $data['store_id'];
        }

        foreach ($stores as $storeId) {
            if (!$positions) {
                $this->merchandisingHelper->deleteQueryRule(
                    $storeId,
                    $landingPageId,
                    'landingpage'
                );
            } else {
                $this->merchandisingHelper->saveQueryRule(
                    $storeId,
                    $landingPageId,
                    $positions,
                    'landingpage',
                    $data['query']
                );
            }
        }
    }
}
