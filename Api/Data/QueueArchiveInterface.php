<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Interface QueueArchiveInterface
 *
 * @package Algolia\AlgoliaSearch\Api\Data
 */
interface QueueArchiveInterface
{
    public const ARCHIVE_ID = 'archive_id';
    public const PID = 'pid';
    public const CLASS_NAME = 'class';
    public const METHOD_NAME = 'method';
    public const DATA = 'data';
    public const RETRIES = 'retries';
    public const ERROR_LOG = 'error_log';
    public const DATA_SIZE = 'data_size';
    public const IS_FULL_REINDEX = 'is_full_reindex';
    public const CREATED_AT = 'created_at';
    public const PROCESSED_AT = 'processed_at';
    public const SUCCESS = 'success';
    public const DEBUG = 'debug';

    /**
     * Get archive ID
     *
     * @return int|null
     */
    public function getArchiveId();

    /**
     * Set archive ID
     *
     * @param int $id
     *
     * @return $this
     */
    public function setArchiveId($id);

    /**
     * Get PID
     *
     * @return int|null
     */
    public function getPid();

    /**
     * Set PID
     *
     * @param int $pid
     *
     * @return $this
     */
    public function setPid($pid);

    /**
     * Get class name
     *
     * @return string|null
     */
    public function getClassName();

    /**
     * Set class name
     *
     * @param string $className
     *
     * @return $this
     */
    public function setClassName($className);

    /**
     * Get method name
     *
     * @return string|null
     */
    public function getMethodName();

    /**
     * Set method name
     *
     * @param string $methodName
     *
     * @return $this
     */
    public function setMethodName($methodName);

    /**
     * Get data field
     *
     * @return string|null
     */
    public function getDataField();

    /**
     * Set data field
     *
     * @param string $dataField
     *
     * @return $this
     */
    public function setDataField($dataField);

    /**
     * Get retries
     *
     * @return int
     */
    public function getRetries();

    /**
     * Set retries
     *
     * @param int $retries
     *
     * @return $this
     */
    public function setRetries($retries);

    /**
     * Get error log
     *
     * @return string|null
     */
    public function getErrorLog();

    /**
     * Set error log
     *
     * @param string $errorLog
     *
     * @return $this
     */
    public function setErrorLog($errorLog);

    /**
     * Get data size
     *
     * @return int|null
     */
    public function getDataSize();

    /**
     * Set data size
     *
     * @param int $dataSize
     *
     * @return $this
     */
    public function setDataSize($dataSize);

    /**
     * Get whether the job is part of a full reindex
     *
     * @return bool
     */
    public function getIsFullReindex();

    /**
     * Set whether the job is part of a full reindex
     *
     * @param bool $isFullReindex
     *
     * @return $this
     */
    public function setIsFullReindex($isFullReindex);

    /**
     * Get creation date and time
     *
     * @return string
     */
    public function getCreatedAt();

    /**
     * Set creation date and time
     *
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get processing date and time
     *
     * @return string|null
     */
    public function getProcessedAt();

    /**
     * Set processing date and time
     *
     * @param string $processedAt
     *
     * @return $this
     */
    public function setProcessedAt($processedAt);

    /**
     * Get whether the job was successful
     *
     * @return bool
     */
    public function getSuccess();

    /**
     * Set whether the job was successful
     *
     * @param bool $success
     *
     * @return $this
     */
    public function setSuccess($success);

    /**
     * Get debug info
     *
     * @return string|null
     */
    public function getDebug();

    /**
     * Set debug info
     *
     * @param string $debug
     *
     * @return $this
     */
    public function setDebug($debug);
}

