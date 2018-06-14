<?php
/**
 * Module Algolia Algoliasearcgh
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends AbstractAction
{
    /**
     * Execute the action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');

        $excluded = $this->getRequest()->getParam('excluded');
        if (!is_null($excluded) && $excluded === "false") {
            try {
                $this->jobFactory->create()->getResource()->deleteAll();
                $this->messageManager->addSuccessMessage(
                    __('All the jobs have been deleted.')
                );
                return $resultRedirect;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect;
            }
        }

        $jobIds = $this->getJobIds();

        if (count($jobIds)<1) {
            $this->messageManager->addErrorMessage(__('Please select job(s).'));
            return $resultRedirect;
        }

        try {
            $this->jobFactory->create()->getResource()->deleteIds($jobIds);
            $this->messageManager->addSuccessMessage(
                __('Total of %1 job(s) were deleted.', count($jobIds))
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect;
    }

    /**
     * Get the job ids
     *
     * @return int[]
     */
    protected function getJobIds()
    {
        $jobIds = $this->getRequest()->getParam('selected');

        if (is_null($jobIds)) {
            return [];
        }

        foreach ($jobIds as $key => $value) {
            $value = (int) $value;

            if ($value<1) {
                unset($jobIds[$key]);
                continue;
            }

            $jobIds[$key] = $value;
        }

        $jobIds = array_values($jobIds);

        return $jobIds;
    }
}
