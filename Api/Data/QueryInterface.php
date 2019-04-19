<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Query Data Interface
 *
 * @api
 */
interface QueryInterface
{
    const TABLE_NAME = 'algoliasearch_query';

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const FIELD_QUERY_ID = 'query_id';
    const FIELD_STORE_ID = 'store_id';
    const FIELD_QUERY_TEXT = 'query_text';
    const FIELD_BANNER_IMAGE = 'banner_image';
    const FIELD_BANNER_URL = 'banner_url';
    const FIELD_BANNER_ALT = 'banner_alt';
    const FIELD_BANNER_CONTENT = 'banner_content';
    const FIELD_CREATED_AT = 'created_at';
    /**#@-*/

    /**
     * Get field: query_id
     *
     * @return int|null
     */
    public function getQueryId();

    /**
     * Get field: store_id
     *
     * @return int|null
     */
    public function getStoreId();

    /**
     * Get field: query_text
     *
     * @return string
     */
    public function getQueryText();

    /**
     * Get field: banner_image
     *
     * @return string
     */
    public function getBannerImage();

    /**
     * Get field: banner_url
     *
     * @return string
     */
    public function getBannerUrl();

    /**
     * Get field: banner_alt
     *
     * @return string
     */
    public function getBannerAlt();

    /**
     * Get field: banner_content
     *
     * @return string
     */
    public function getBannerContent();

    /**
     * Get field: created_at
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set field: query_id
     *
     * @param int $value
     *
     * @return QueryInterface
     */
    public function setQueryId($value);

    /**
     * Set field: store_id
     *
     * @param int $value
     *
     * @return QueryInterface
     */
    public function setStoreId($value);

    /**
     * Set field: query_text
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setQueryText($value);

    /**
     * Set field: banner_image
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setBannerImage($value);

    /**
     * Set field: banner_url
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setBannerUrl($value);

    /**
     * Set field: banner_alt
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setBannerAlt($value);

    /**
     * Set field: banner_content
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setBannerContent($value);

    /**
     * Set field: created_at
     *
     * @param string $value
     *
     * @return QueryInterface
     */
    public function setCreatedAt($value);
}
