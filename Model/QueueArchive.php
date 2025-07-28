<?php
namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\QueueArchiveInterface;
use Magento\Framework\Model\AbstractModel;

class QueueArchive extends AbstractModel implements QueueArchiveInterface
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(\Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive::class);
    }

    /**
     * Get archive ID
     *
     * @return int|null
     */
    public function getArchiveId()
    {
        return $this->_getData(self::ARCHIVE_ID);
    }

    /**
     * Set archive ID
     *
     * @param int $id
     * @return $this
     */
    public function setArchiveId($id)
    {
        return $this->setData(self::ARCHIVE_ID, $id);
    }

    /**
     * Get PID
     *
     * @return int|null
     */
    public function getPid()
    {
        return $this->_getData(self::PID);
    }

    /**
     * Set PID
     *
     * @param int $pid
     * @return $this
     */
    public function setPid($pid)
    {
        return $this->setData(self::PID, $pid);
    }

    /**
     * Get class name
     *
     * @return string|null
     */
    public function getClassName()
    {
        return $this->_getData(self::CLASS_NAME);
    }

    /**
     * Set class name
     *
     * @param string $className
     * @return $this
     */
    public function setClassName($className)
    {
        return $this->setData(self::CLASS_NAME, $className);
    }

    /**
     * Get method name
     *
     * @return string|null
     */
    public function getMethodName()
    {
        return $this->_getData(self::METHOD_NAME);
    }

    /**
     * Set method name
     *
     * @param string $methodName
     * @return $this
     */
    public function setMethodName($methodName)
    {
        return $this->setData(self::METHOD_NAME, $methodName);
    }

    /**
     * Get data field
     *
     * @return string|null
     */
    public function getDataField()
    {
        return $this->_getData(self::DATA);
    }

    /**
     * Set data field
     *
     * @param string $dataField
     * @return $this
     */
    public function setDataField($dataField)
    {
        return $this->setData(self::DATA, $dataField);
    }

    /**
     * Get retries
     *
     * @return int
     */
    public function getRetries()
    {
        return $this->_getData(self::RETRIES);
    }

    /**
     * Set retries
     *
     * @param int $retries
     * @return $this
     */
    public function setRetries($retries)
    {
        return $this->setData(self::RETRIES, $retries);
    }

    /**
     * Get error log
     *
     * @return string|null
     */
    public function getErrorLog()
    {
        return $this->_getData(self::ERROR_LOG);
    }

    /**
     * Set error log
     *
     * @param string $errorLog
     * @return $this
     */
    public function setErrorLog($errorLog)
    {
        return $this->setData(self::ERROR_LOG, $errorLog);
    }

    /**
     * Get data size
     *
     * @return int|null
     */
    public function getDataSize()
    {
        return $this->_getData(self::DATA_SIZE);
    }

    /**
     * Set data size
     *
     * @param int $dataSize
     * @return $this
     */
    public function setDataSize($dataSize)
    {
        return $this->setData(self::DATA_SIZE, $dataSize);
    }

    /**
     * Get whether the job is part of a full reindex
     *
     * @return bool
     */
    public function getIsFullReindex()
    {
        return $this->_getData(self::IS_FULL_REINDEX);
    }

    /**
     * Set whether the job is part of a full reindex
     *
     * @param bool $isFullReindex
     * @return $this
     */
    public function setIsFullReindex($isFullReindex)
    {
        return $this->setData(self::IS_FULL_REINDEX, $isFullReindex);
    }

    /**
     * Get creation date and time
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->_getData(self::CREATED_AT);
    }

    /**
     * Set creation date and time
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Get processing date and time
     *
     * @return string|null
     */
    public function getProcessedAt()
    {
        return $this->_getData(self::PROCESSED_AT);
    }

    /**
     * Set processing date and time
     *
     * @param string $processedAt
     * @return $this
     */
    public function setProcessedAt($processedAt)
    {
        return $this->setData(self::PROCESSED_AT, $processedAt);
    }

    /**
     * Get whether the job was successful
     *
     * @return bool
     */
    public function getSuccess()
    {
        return $this->_getData(self::SUCCESS);
    }

    /**
     * Set whether the job was successful
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess($success)
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * Get debug info
     *
     * @return string|null
     */
    public function getDebug()
    {
        return $this->_getData(self::DEBUG);
    }

    /**
     * Set debug info
     *
     * @param string $debug
     * @return $this
     */
    public function setDebug($debug)
    {
        return $this->setData(self::DEBUG, $debug);
    }

}
