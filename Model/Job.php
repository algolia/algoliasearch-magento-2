<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;

/**
 * @api
 *
 * @method int getPid()
 * @method int getStoreId()
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
class Job extends \Magento\Framework\Model\AbstractModel implements JobInterface
{
    protected $_eventPrefix = 'algoliasearch_queue_job';

    public function __construct(
        protected Context                $context,
        protected Registry               $registry,
        protected ObjectManagerInterface $objectManager,
        protected ?AbstractResource      $resource = null,
        protected ?AbstractDb            $resourceCollection = null,
        array                            $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Job::class);
    }

    /**
     * @return $this
     * @throws AlreadyExistsException|\Exception
     *
     */
    public function execute(): Job
    {
        $model = $this->objectManager->get($this->getClass());
        $method = $this->getMethod();
        $data = $this->getDecodedData();

        $this->setRetries((int) $this->getRetries() + 1);

        call_user_func_array([$model, $method], $data);

        $this->save();

        return $this;
    }

    /**
     * @return $this
     */
    public function prepare(): Job
    {
        if ($this->getMergedIds() === null) {
            $this->setMergedIds([$this->getId()]);
        }

        if ($this->getDecodedData() === null) {
            $decodedData = json_decode((string) $this->getData('data'), true);

            $this->setDecodedData($decodedData);

            if (isset($decodedData['storeId'])) {
                $this->setStoreId($decodedData['storeId']);
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
    public function canMerge(Job $job, $maxJobDataSize): bool
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

        if (!isset($decodedData['entityIds']) || count($decodedData['entityIds']) <= 0) {
            return false;
        }

        $candidateDecodedData = $job->getDecodedData();

        if (!isset($candidateDecodedData['entityIds']) || count($candidateDecodedData['entityIds']) <= 0) {
            return false;
        }

        if (count($decodedData['entityIds']) + count($candidateDecodedData['entityIds']) > $maxJobDataSize) {
            return false;
        }

        return true;
    }

    /**
     * @param Job $mergedJob
     *
     * @return Job
     */
    public function merge(Job $mergedJob): Job
    {
        $mergedIds = $this->getMergedIds();
        array_push($mergedIds, $mergedJob->getId());

        $this->setMergedIds($mergedIds);

        $decodedData = $this->getDecodedData();
        $mergedJobDecodedData = $mergedJob->getDecodedData();

        $dataSize = $this->getDataSize();

        if (isset($decodedData['entityIds'])) {
            $decodedData['entityIds'] = array_unique(array_merge(
                $decodedData['entityIds'],
                $mergedJobDecodedData['entityIds']
            ));

            $dataSize = count($decodedData['entityIds']);
        }

        $this->setDecodedData($decodedData);
        $this->setDataSize($dataSize);

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultValues(): array
    {
        $values = [];

        return $values;
    }

    /**
     * @return string
     */
    public function getStatus(): string
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
     * @throws AlreadyExistsException
     *
     * @return Job
     */
    public function saveError(\Exception $e): Job
    {
        $this->setErrorLog($e->getMessage());
        $this->getResource()->save($this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getClass(): string
    {
        return $this->getData(self::FIELD_CLASS);
    }

    /**
     * @inheritdoc
     */
    public function setClass(string $class): JobInterface
    {
        return $this->setData(self::FIELD_CLASS, $class);
    }

    /**
     * @inheritdoc
     */
    public function getMethod(): string
    {
        return $this->getData(self::FIELD_METHOD);
    }

    /**
     * @inheritdoc
     */
    public function setMethod(string $method): JobInterface
    {
        return $this->setData(self::FIELD_METHOD, $method);
    }

    /**
     * @inheritdoc
     */
    public function getBody(): string
    {
        return $this->getData(self::FIELD_DATA);
    }

    /**
     * @inheritdoc
     */
    public function setBody(string $data): JobInterface
    {
        return $this->setData(self::FIELD_DATA, $data);
    }

    /**
     * @inheritdoc
     */
    public function getBodySize(): int
    {
        return $this->getData(self::FIELD_DATA_SIZE);
    }

    /**
     * @inheritdoc
     */
    public function setBodySize(int $size): JobInterface
    {
        return $this->setData(self::FIELD_DATA_SIZE, $size);
    }
}
