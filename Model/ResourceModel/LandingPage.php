<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Algolia\AlgoliaSearch\Api\Data\LandingPageInterface;
use Algolia\AlgoliaSearch\Model\LandingPageUrlRewriteGenerator;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class LandingPage extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * @var LandingPageUrlRewriteGenerator
     */
    protected $landingPageUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    protected $urlPersist;

    /**
     * @param Context $context
     * @param LandingPageUrlRewriteGenerator $landingPageUrlRewriteGenerator
     * @param UrlPersistInterface $urlPersist
     */
    public function __construct(
        Context $context,
        LandingPageUrlRewriteGenerator $landingPageUrlRewriteGenerator,
        UrlPersistInterface $urlPersist,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->landingPageUrlRewriteGenerator = $landingPageUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
    }

    protected function _construct()
    {
        $this->_init(LandingPageInterface::TABLE_NAME, LandingPageInterface::FIELD_LANDING_PAGE_ID);
    }

    /**
     * Create url rewrite before saving
     *
     * @param AbstractModel $object
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        if ($object->dataHasChangedFor('url_key') || $object->dataHasChangedFor('store_id')) {
            $urls = $this->landingPageUrlRewriteGenerator->generate($object);

            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $object->getId(),
                UrlRewrite::ENTITY_TYPE => LandingPageUrlRewriteGenerator::ENTITY_TYPE,
            ]);
            $this->urlPersist->replace($urls);
        }

        return parent::_afterSave($object);
    }

    /**
     * Delete url rewrite after deletion
     *
     * @param AbstractModel $object
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    protected function _afterDelete(AbstractModel $object)
    {
        $this->urlPersist->deleteByData(
            [
                UrlRewrite::ENTITY_ID => $object->getId(),
                UrlRewrite::ENTITY_TYPE => LandingPageUrlRewriteGenerator::ENTITY_TYPE,
            ]
        );

        return parent::_afterDelete($object);
    }

    /**
     * Check if landing page identifier exist for specific store
     * return page id if page exists
     *
     * @param string $identifier
     * @param int $storeId
     * @param string $date
     *
     * @return int
     */
    public function checkIdentifier($identifier, $storeId, $date)
    {
        $select = $this->getConnection()
            ->select()
            ->from(['lp' => $this->getMainTable()])
            ->where('url_key = ?', $identifier)
            ->where('is_active = ?', 1)
            ->where('store_id IN (?)', [Store::DEFAULT_STORE_ID, $storeId])
            ->where('date_from IS NULL OR date_from <= ?', $date)
            ->where('date_to IS NULL OR date_to >= ?', $date)
            ->order('store_id DESC')
            ->limit(1);

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * Check if the url_key already exists in the url_rewrite table
     * return  id if page exists
     *
     * @param string $urlKey
     * @param int $storeId
     * @param int $landingPageId
     *
     * @return int
     */
    public function checkUrlRewriteTable($urlKey, $storeId = null, $landingPageId = null)
    {
        $select = $this->getConnection()
            ->select()
            ->from(['ur' => $this->getConnection()->getTableName('url_rewrite')])
            ->where('request_path = ?', $urlKey);

        // Only check a particular store if specified
        if (!is_null($storeId) && $storeId != 0) {
            $select->where('store_id = ?', $storeId);
        }

        // Handle the already existing url rewrite for the landing page
        if (!is_null($landingPageId) && $landingPageId != 0) {
            $select->where('!(entity_type = "landing-page" AND entity_id = ?)', $landingPageId);
        }

        $select->limit(1);

        return $this->getConnection()->fetchOne($select);
    }
}
