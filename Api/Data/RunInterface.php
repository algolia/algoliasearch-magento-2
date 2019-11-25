<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Run Data Interface
 *
 * @api
 */
interface RunInterface
{
    const TABLE_NAME = 'algoliasearch_queue_log';

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const FIELD_RUN_ID = 'id';
    const FIELD_STARTED = 'started';
    const FIELD_DURATION = 'duration';
    const FIELD_PROCESSED_JOBS = 'processed_jobs';
    const FIELD_WITH_EMPTY_QUEUE = 'with_empty_queue';
    /**#@-*/
}
