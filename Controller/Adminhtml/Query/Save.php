<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Algolia\AlgoliaSearch\Helper\MerchandisingHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;
use Algolia\AlgoliaSearch\Model\QueryFactory;
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
     * @param QueryFactory $queryFactory
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
        QueryFactory $queryFactory,
        MerchandisingHelper $merchandisingHelper,
        ProxyHelper $proxyHelper,
        StoreManagerInterface $storeManager,
        DataPersistorInterface $dataPersistor
    ) {
        $this->dataPersistor = $dataPersistor;

        parent::__construct(
            $context,
            $coreRegistry,
            $queryFactory,
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
            if (empty($data['query_id'])) {
                $data['query_id'] = null;
            }
            $queryId = $data['query_id'];

            /** @var \Algolia\AlgoliaSearch\Model\Query $query */
            $query = $this->queryFactory->create();

            if ($queryId) {
                $query->getResource()->load($query, $queryId);

                if (!$query->getId()) {
                    $this->messageManager->addErrorMessage(__('This query does not exist.'));

                    return $resultRedirect->setPath('*/*/');
                }
            }

            if (isset($data['banner_image'][0]['name']) && isset($data['banner_image'][0]['tmp_name'])) {
                $data['banner_image'] = $data['banner_image'][0]['name'];
                $this->imageUploader = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    'Algolia\AlgoliaSearch\QueryImageUpload'
                );
                $this->imageUploader->moveFileFromTmp($data['banner_image']);
            } elseif (isset($data['banner_image'][0]['image']) && !isset($data['banner_image'][0]['tmp_name'])) {
                $data['banner_image'] = $data['banner_image'][0]['image'];
            } else {
                $data['banner_image'] = null;
            }

            $query->setData($data);
            $query->setCreatedAt(time());

            try {
                $query->getResource()->save($query);

                if (isset($data['algolia_merchandising_positions']) && $data['algolia_merchandising_positions'] != ''
                    || !is_null($data['banner_image'])) {
                    $this->manageQueryRules($query->getId(), $data);
                }

                $this->messageManager->addSuccessMessage(__('The query has been saved.'));
                $this->dataPersistor->clear('algolia_algoliasearch_query');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $query->getId()]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the query. %1', $e->getMessage())
                );
            }

            $this->dataPersistor->set('query', $data);

            return $resultRedirect->setPath('*/*/edit', ['id' => $queryId]);
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function manageQueryRules($queryId, $data)
    {
        $positions = json_decode($data['algolia_merchandising_positions'], true);
        $stores = [];
        if ($data['store_id'] == 0) {
            $stores = $this->getActiveStores();
        } else {
            $stores[] = $data['store_id'];
        }

        $bannerContent = $this->prepareBannerContent($data);

        foreach ($stores as $storeId) {
            if (!$positions && is_null($bannerContent)) {
                $this->merchandisingHelper->deleteQueryRule(
                    $storeId,
                    $queryId,
                    'query'
                );
            } else {
                $this->merchandisingHelper->saveQueryRule(
                    $storeId,
                    $queryId,
                    $positions,
                    'query',
                    $data['query_text'],
                    $bannerContent
                );
            }
        }
    }

    /**
     * @param array $data
     *
     * @return string|null
     */
    private function prepareBannerContent($data)
    {
        $content = null;

        if (isset($data['banner_image']) && $data['banner_image']) {
            $baseurl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            $bannerUrl = $baseurl . 'algolia_img/' . $data['banner_image'];
            $banner = '<img src="' . $bannerUrl . '" alt="' . $data['banner_alt'] . '" />';
            if (isset($data['banner_url']) && $data['banner_url']) {
                $content = '<a href="' . $data['banner_url'] . '" target="_blank" >' . $banner . '</a>';
            } else {
                $content = $banner;
            }
        }

        if (isset($data['banner_content']) && $data['banner_content']) {
            $content .= '<p>' . $data['banner_content'] . '</p>';
        }

        return $content;
    }
}
