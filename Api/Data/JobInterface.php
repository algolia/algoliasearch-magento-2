<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Job Data Interface
 *
 * @api
 */
interface JobInterface
{
    const TABLE_NAME = 'algoliasearch_queue';

    const STATUS_NEW = 'new';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ERROR = 'error';
    const STATUS_COMPLETE = 'complete';

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const FIELD_JOB_ID = 'job_id';
    const FIELD_CREATED = 'created';
    const FIELD_PID = 'pid';
    const FIELD_CLASS = 'class';
    const FIELD_METHOD = 'method';
    const FIELD_DATA = 'data';
    const FIELD_MAX_RETRIES = 'max_retries';
    const FIELD_RETRIES = 'retries';
    const FIELD_ERROR_LOG = 'error_log';
    const FIELD_DATA_SIZE = 'data_size';
    /**#@-*/
}
