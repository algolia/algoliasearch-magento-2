<?php

namespace Algolia\AlgoliaSearch\Api\Data;

/**
 * Landing Page Data Interface
 *
 * @api
 */
interface LandingPageInterface
{
    const TABLE_NAME = 'algoliasearch_landing_page';

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const FIELD_LANDING_PAGE_ID = 'landing_page_id';
    const FIELD_STORE_ID = 'store_id';
    const FIELD_URL_KEY = 'url_key';
    const FIELD_IS_ACTIVE = 'is_active';
    const FIELD_TITLE = 'title';
    const FIELD_DATE_FROM = 'date_from';
    const FIELD_DATE_TO = 'date_to';
    const FIELD_META_TITLE = 'meta_title';
    const FIELD_META_DESCRIPTION = 'meta_description';
    const FIELD_META_KEYWORDS = 'meta_keywords';
    const FIELD_CONTENT = 'content';
    const FIELD_QUERY = 'query';
    const FIELD_CONFIGURATION = 'configuration';
    const FIELD_CUSTOM_JS = 'custom_js';
    const FIELD_CUSTOM_CSS = 'custom_css';
    /**#@-*/

    /**
     * Get field: landing_page_id
     *
     * @return int|null
     */
    public function getLandingPageId();

    /**
     * Get field: store_id
     *
     * @return int|null
     */
    public function getStoreId();

    /**
     * Get field: url_key
     *
     * @return string
     */
    public function getUrlKey();

    /**
     * Get field: is_active
     *
     * @return bool
     */
    public function getIsActive();

    /**
     * Get field: title
     *
     * @return string
     */
    public function getTitle();

    /**
     * Get field: date_from
     *
     * @return string
     */
    public function getDateFrom();

    /**
     * Get field: date_to
     *
     * @return string
     */
    public function getDateTo();

    /**
     * Get field: meta_title
     *
     * @return string
     */
    public function getMetaTitle();

    /**
     * Get field: meta_description
     *
     * @return string
     */
    public function getMetaDescription();

    /**
     * Get field: meta_keywords
     *
     * @return string
     */
    public function getMetaKeywords();

    /**
     * Get field: content
     *
     * @return string
     */
    public function getContent();

    /**
     * Get field: query
     *
     * @return string
     */
    public function getQuery();

    /**
     * Get field: configuration
     *
     * @return string
     */
    public function getConfiguration();

    /**
     * Get field: custom_js
     *
     * @return string
     */
    public function getCustomJs();

    /**
     * Get field: custom_css
     *
     * @return string
     */
    public function getCustomCss();

    /**
     * Set field: landing_page_id
     *
     * @param int $value
     *
     * @return LandingPageInterface
     */
    public function setLandingPageId($value);

    /**
     * Set field: store_id
     *
     * @param int $value
     *
     * @return LandingPageInterface
     */
    public function setStoreId($value);

    /**
     * Set field: url_key
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setUrlKey($value);

    /**
     * Set field: is_active
     *
     * @param bool $value
     *
     * @return LandingPageInterface
     */
    public function setIsActive($value);

    /**
     * Set field: title
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setTitle($value);

    /**
     * Set field: date_from
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setDateFrom($value);

    /**
     * Set field: date_to
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setDateTo($value);

    /**
     * Set field: meta_title
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setMetaTitle($value);

    /**
     * Set field: meta_description
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setMetaDescription($value);

    /**
     * Set field: meta_keywords
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setMetaKeywords($value);

    /**
     * Set field: content
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setContent($value);

    /**
     * Set field: query
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setQuery($value);

    /**
     * Set field: configuration
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setConfiguration($value);

    /**
     * Set field: custom_js
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setCustomJs($value);

    /**
     * Set field: custom_css
     *
     * @param string $value
     *
     * @return LandingPageInterface
     */
    public function setCustomCss($value);
}
