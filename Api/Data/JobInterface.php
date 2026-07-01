<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Job Data Interface
 *
 * @api
 */
interface JobInterface
{
    public const TABLE_NAME = 'algoliasearch_queue';

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ERROR = 'error';
    public const STATUS_COMPLETE = 'complete';

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    public const FIELD_JOB_ID = 'job_id';
    public const FIELD_CREATED = 'created';
    public const FIELD_PID = 'pid';
    public const FIELD_CLASS = 'class';
    public const FIELD_METHOD = 'method';
    public const FIELD_DATA = 'data';
    public const FIELD_MAX_RETRIES = 'max_retries';
    public const FIELD_RETRIES = 'retries';
    public const FIELD_ERROR_LOG = 'error_log';
    public const FIELD_DATA_SIZE = 'data_size';

    public function getClass(): string;

    /**
     *
     * @return $this
     */
    public function setClass(string $class): self;

    public function getMethod(): string;

    /**
     *
     * @return $this
     */
    public function setMethod(string $method): self;

    public function getBody(): string;

    /**
     *
     * @return $this
     */
    public function setBody(string $data): self;

    public function getBodySize(): int;

    /**
     *
     * @return $this
     */
    public function setBodySize(int $size): self;
}
