<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\QueueArchive;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Algolia\AlgoliaSearch\Api\QueueArchiveRepositoryInterface;
use Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive\CollectionFactory;
use Magento\Framework\Controller\ResultInterface;
use RuntimeException;

class MassDelete extends Action
{
    /** @var QueueArchiveRepositoryInterface */
    protected $queueArchiveRepository;

    /** @var Filter */
    protected $filter;

    /** @var ColectionFactory */
    protected $collectionFactory;

    public function __construct(
        Action\Context $context,
        Filter $filter,
        QueueArchiveRepositoryInterface $queueArchiveRepository,
        CollectionFactory $collectionFactory
    ) {
        $this->queueArchiveRepository = $queueArchiveRepository;
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * Delete Action
     *
     * @throws LocalizedException
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $dataDeleted = 0;
        $collection = $this->filter->getCollection($this->collectionFactory->create());

        try {
            foreach ($collection as $item) {
                $this->queueArchiveRepository->deleteById($item->getArchiveId());
                $dataDeleted++;
            }
            $this->messageManager->addSuccess(__('A total of %1 record(s) have been deleted.', $dataDeleted));
        } catch (LocalizedException|RuntimeException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}