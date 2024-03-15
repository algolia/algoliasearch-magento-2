<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Model\LandingPageFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

class Save extends AbstractAction
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var CollectionFactory
     */
    protected $customerGroupCollectionFactory;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var SessionManagerInterface
     */
    protected $backendSession;

    /**
     * PHP Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param SessionManagerInterface $backendSession
     * @param LandingPageFactory $landingPageFactory
     * @param MerchandisingHelper $merchandisingHelper
     * @param StoreManagerInterface $storeManager
     * @param DataPersistorInterface $dataPersistor
     * @param CollectionFactory $customerGroupCollectionFactory
     * @return Save
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        SessionManagerInterface $backendSession,
        LandingPageFactory $landingPageFactory,
        MerchandisingHelper $merchandisingHelper,
        StoreManagerInterface $storeManager,
        DataPersistorInterface $dataPersistor,
        CollectionFactory $customerGroupCollectionFactory,
        ConfigHelper $configHelper
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->configHelper = $configHelper;
        parent::__construct(
            $context,
            $backendSession,
            $landingPageFactory,
            $merchandisingHelper,
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
                if ($this->configHelper->isCustomerGroupsEnabled($data['store_id'])) {
                    $configuration = json_decode($data['algolia_configuration'], true);
                    $priceConfig = $configuration['price'.$data['price_key']];
                    $customerGroups = $this->customerGroupCollectionFactory->create();
                    $store = $this->storeManager->getStore($data['store_id']);
                    $baseCurrencyCode = $store->getBaseCurrencyCode();
                    foreach ($customerGroups as $group) {
                        $groupId = (int) $group->getData('customer_group_id');
                        $configuration['price.'.$baseCurrencyCode.'.group_'.$groupId] = $priceConfig;
                    }
                    $data['configuration'] = json_encode($configuration);
                }
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

    /**
     * @param int $landingPageId
     * @param array<string, mixed> $data
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function manageQueryRules(int $landingPageId, array $data): void
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
