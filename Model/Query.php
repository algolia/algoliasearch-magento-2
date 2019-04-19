<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\QueryInterface;
use Magento\Framework\DataObject\IdentityInterface;

class Query extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, QueryInterface
{
    const CACHE_TAG = 'algoliasearch_query';

    protected $_cacheTag = 'algoliasearch_query';

    protected $_eventPrefix = 'algoliasearch_query';

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Algolia\AlgoliaSearch\Model\ResourceModel\Query::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryId()
    {
        return $this->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId()
    {
        return (int) $this->getData(self::FIELD_STORE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryText()
    {
        return (string) $this->getData(self::FIELD_QUERY_TEXT);
    }

    /**
     * {@inheritdoc}
     */
    public function getBannerImage()
    {
        return (string) $this->getData(self::FIELD_BANNER_IMAGE);
    }

    /**
     * {@inheritdoc}
     */
    public function getBannerUrl()
    {
        return (string) $this->getData(self::FIELD_BANNER_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function getBannerAlt()
    {
        return (string) $this->getData(self::FIELD_BANNER_ALT);
    }

    /**
     * {@inheritdoc}
     */
    public function getBannerContent()
    {
        return (string) $this->getData(self::FIELD_BANNER_CONTENT);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return (string) $this->getData(self::FIELD_CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setQueryId($value)
    {
        return $this->setId((int) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreId($value)
    {
        return $this->setData(self::FIELD_STORE_ID, (int) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setQueryText($value)
    {
        return $this->setData(self::FIELD_QUERY_TEXT, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setBannerImage($value)
    {
        return $this->setData(self::FIELD_BANNER_IMAGE, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setBannerUrl($value)
    {
        return $this->setData(self::FIELD_BANNER_URL, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setBannerAlt($value)
    {
        return $this->setData(self::FIELD_BANNER_ALT, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setBannerContent($value)
    {
        return $this->setData(self::FIELD_BANNER_CONTENT, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($value)
    {
        return $this->setData(self::FIELD_CREATED_AT, (string) $value);
    }
}
