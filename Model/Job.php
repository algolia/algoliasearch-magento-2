<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * @api
 *
 * @method int getPid()
 * @method int getStoreId()
 * @method string getClass()
 * @method string getMethod()
 * @method int getDataSize()
 * @method int getRetries()
 * @method int getMaxRetries()
 * @method array getDecodedData()
 * @method array getMergedIds()
 * @method $this setErrorLog(string $message)
 * @method $this setPid($pid)
 * @method $this setRetries($retries)
 * @method $this setStoreId($storeId)
 * @method $this setDataSize($dataSize)
 * @method $this setDecodedData($decodedData)
 * @method $this setMergedIds($mergedIds)
 */
class Job extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, JobInterface
{
    const CACHE_TAG = 'algoliasearch_queue_job';

    protected $_cacheTag = 'algoliasearch_queue_job';

    protected $_eventPrefix = 'algoliasearch_queue_job';

    /** @var \Magento\Framework\ObjectManagerInterface */
    protected $objectManager;

    /**
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\ObjectManagerInterface               $objectManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param array                                                   $data
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
        $this->_init(\Algolia\AlgoliaSearch\Model\ResourceModel\Job::class);
    }

    /**
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     *
     * @return $this
     */
    public function execute()
    {
        $model = $this->objectManager->get($this->getClass());
        $method = $this->getMethod();
        $data = $this->getDecodedData();

        $this->setRetries((int) $this->getRetries() + 1);

        call_user_func_array([$model, $method], $data);

        $this->getResource()->save($this);

        $this->save();

        return $this;
    }

    /**
     * @return $this
     */
    public function prepare()
    {
        if ($this->getMergedIds() === null) {
            $this->setMergedIds([$this->getId()]);
        }

        if ($this->getDecodedData() === null) {
            $decodedData = json_decode($this->getData('data'), true);

            $this->setDecodedData($decodedData);

            if (isset($decodedData['store_id'])) {
                $this->setStoreId($decodedData['store_id']);
            }
        }

        return $this;
    }

    /**
     * @param Job $job
     * @param $maxJobDataSize
     *
     * @return bool
     */
    public function canMerge(Job $job, $maxJobDataSize)
    {
        if ($this->getClass() !== $job->getClass()) {
            return false;
        }

        if ($this->getMethod() !== $job->getMethod()) {
            return false;
        }

        if ($this->getStoreId() !== $job->getStoreId()) {
            return false;
        }

        $decodedData = $this->getDecodedData();

        if ((!isset($decodedData['product_ids']) || count($decodedData['product_ids']) <= 0)
            && (!isset($decodedData['category_ids']) || count($decodedData['category_ids']) < 0)) {
            return false;
        }

        $candidateDecodedData = $job->getDecodedData();

        if ((!isset($candidateDecodedData['product_ids']) || count($candidateDecodedData['product_ids']) <= 0)
            && (!isset($candidateDecodedData['category_ids']) || count($candidateDecodedData['category_ids']) < 0)) {
            return false;
        }

        if (isset($decodedData['product_ids'])
            && count($decodedData['product_ids']) + count($candidateDecodedData['product_ids']) > $maxJobDataSize) {
            return false;
        }

        if (isset($decodedData['category_ids'])
            && count($decodedData['category_ids']) + count($candidateDecodedData['category_ids']) > $maxJobDataSize) {
            return false;
        }

        return true;
    }

    /**
     * @param Job $mergedJob
     *
     * @return Job
     */
    public function merge(Job $mergedJob)
    {
        $mergedIds = $this->getMergedIds();
        array_push($mergedIds, $mergedJob->getId());

        $this->setMergedIds($mergedIds);

        $decodedData = $this->getDecodedData();
        $mergedJobDecodedData = $mergedJob->getDecodedData();

        $dataSize = $this->getDataSize();

        if (isset($decodedData['product_ids'])) {
            $decodedData['product_ids'] = array_unique(array_merge(
                $decodedData['product_ids'],
                $mergedJobDecodedData['product_ids']
            ));

            $dataSize = count($decodedData['product_ids']);
        } elseif (isset($decodedData['category_ids'])) {
            $decodedData['category_ids'] = array_unique(array_merge(
                $decodedData['category_ids'],
                $mergedJobDecodedData['category_ids']
            ));

            $dataSize = count($decodedData['category_ids']);
        }

        $this->setDecodedData($decodedData);
        $this->setDataSize($dataSize);

        return $this;
    }

    /**
     * @return array|string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @return array
     */
    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    /**
     * @return string
     */
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
     * @param \Exception $e
     *
     * @throws \Magento\Framework\Exception\AlreadyExistsException
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
