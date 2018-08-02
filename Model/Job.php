<?php
namespace Algolia\AlgoliaSearch\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Algolia\AlgoliaSearch\Api\Data\JobInterface;

class Job extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, JobInterface
{
    const CACHE_TAG = 'algoliasearch_queue_job';

    protected $_cacheTag = 'algoliasearch_queue_job';

    protected $_eventPrefix = 'algoliasearch_queue_job';

    /**
     * Object Manager
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * PHP Constructor
     *
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param array                                                   $data
     *
     * @return Area
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->objectManager = $objectManager;
    }

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Algolia\AlgoliaSearch\Model\ResourceModel\Job');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    public function getStatus()
    {
        $status = JobInterface::STATUS_PROCESSING;

        if (is_null($this->getPid())) {
            $status = JobInterface::STATUS_NEW;
        }

        if ((int) $this->getRetries() >= $this->getMaxRetries()) {
            $status = JobInterface::STATUS_ERROR;
        }

        return $status;
    }

    /**
     * execute the job
     *
     * @return Job
     */
    public function execute()
    {
        $this->setPid(getmypid());
        $jobData = $this->getData();
        $model = $this->objectManager->get($jobData['class']);
        $method = $jobData['method'];
        $data = json_decode($jobData['data'], true);

        $this->setRetries((int) $this->getRetries() + 1);
        call_user_func_array([$model, $method], $data);
        $this->getResource()->save($this);

        return $this;
    }

    /**
     * Save the error
     *
     * @param \Exception $e
     *
     * @return Job
     */
    public function saveError(\Exception $e)
    {
        $this->setErrorLog($e->getMessage());
        $this->getResource()->save($this);

        return $this;
    }
}
