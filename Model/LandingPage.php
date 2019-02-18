<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\LandingPageInterface;
use Magento\Framework\DataObject\IdentityInterface;

class LandingPage extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, LandingPageInterface
{
    const CACHE_TAG = 'algoliasearch_landing_page';

    protected $_cacheTag = 'algoliasearch_landing_page';

    protected $_eventPrefix = 'algoliasearch_landing_page';

    /**
     * Magento Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * {@inheritdoc}
     */
    public function getLandingPageId()
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
    public function getUrlKey()
    {
        return (string) $this->getData(self::FIELD_URL_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function getIsActive()
    {
        return (bool) $this->getData(self::FIELD_IS_ACTIVE);
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle()
    {
        return (string) $this->getData(self::FIELD_TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFrom()
    {
        return (string) $this->getData(self::FIELD_DATE_FROM);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTo()
    {
        return (string) $this->getData(self::FIELD_DATE_TO);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetaTitle()
    {
        return (string) $this->getData(self::FIELD_META_TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetaDescription()
    {
        return (string) $this->getData(self::FIELD_META_DESCRIPTION);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetaKeywords()
    {
        return (string) $this->getData(self::FIELD_META_KEYWORDS);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return (string) $this->getData(self::FIELD_CONTENT);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        return (string) $this->getData(self::FIELD_QUERY);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return (string) $this->getData(self::FIELD_CONFIGURATION);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomJs()
    {
        return (string) $this->getData(self::FIELD_CUSTOM_JS);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomCss()
    {
        return (string) $this->getData(self::FIELD_CUSTOM_CSS);
    }

    /**
     * {@inheritdoc}
     */
    public function setLandingPageId($value)
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
    public function setUrlKey($value)
    {
        return $this->setData(self::FIELD_URL_KEY, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsActive($value)
    {
        return $this->setData(self::FIELD_IS_ACTIVE, (bool) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setTitle($value)
    {
        return $this->setData(self::FIELD_TITLE, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setDateFrom($value)
    {
        return $this->setData(self::FIELD_DATE_FROM, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setDateTo($value)
    {
        return $this->setData(self::FIELD_DATE_TO, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setMetaTitle($value)
    {
        return $this->setData(self::FIELD_META_TITLE, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setMetaDescription($value)
    {
        return $this->setData(self::FIELD_META_DESCRIPTION, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setMetaKeywords($value)
    {
        return $this->setData(self::FIELD_META_KEYWORDS, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setContent($value)
    {
        return $this->setData(self::FIELD_CONTENT, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setQuery($value)
    {
        return $this->setData(self::FIELD_QUERY, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration($value)
    {
        return $this->setData(self::FIELD_CONFIGURATION, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomJs($value)
    {
        return $this->setData(self::FIELD_CUSTOM_JS, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomCss($value)
    {
        return $this->setData(self::FIELD_CUSTOM_CSS, (string) $value);
    }

    /**
     * Check if landing page url key exists for specific store
     * return page id if landing page exists
     *
     * @param string $identifier
     * @param int $storeId
     * @param string $date
     *
     * @return int
     */
    public function checkIdentifier($identifier, $storeId, $date)
    {
        return $this->_getResource()->checkIdentifier($identifier, $storeId, $date);
    }
}
