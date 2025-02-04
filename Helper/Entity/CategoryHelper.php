<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Exception\CategoryEmptyException;
use Algolia\AlgoliaSearch\Exception\CategoryNotActiveException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\RecordBuilder as CategoryRecordBuilder;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category as MagentoCategory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryHelper extends AbstractEntityHelper
{
    use EntityHelperTrait;
    public const INDEX_NAME_SUFFIX = '_categories';
    protected $categoryAttributes;
    protected $rootCategoryId = -1;

    public function __construct(
        protected ManagerInterface          $eventManager,
        protected StoreManagerInterface     $storeManager,
        protected Config                    $eavConfig,
        protected ConfigHelper              $configHelper,
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected CategoryRepository        $categoryRepository,
        protected IndexNameFetcher          $indexNameFetcher,
        protected CategoryRecordBuilder     $categoryRecordBuilder,
    )
    {
        parent::__construct($indexNameFetcher);
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getIndexSettings(?int $storeId = null): array
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];

        foreach ($this->configHelper->getCategoryAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['searchable'] === '1') {
                if ($attribute['order'] === 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] !== '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        $customRankings = $this->configHelper->getCategoryCustomRanking($storeId);

        $customRankingsArr = [];

        foreach ($customRankings as $ranking) {
            $customRankingsArr[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        // Default index settings
        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'customRanking'           => $customRankingsArr,
            'unretrievableAttributes' => $unretrievableAttributes,
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        /** Removed legacy algolia_index_settings_prepare event on 3.15.0 */
        $this->eventManager->dispatch('algolia_categories_index_before_set_settings', [
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]);
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAdditionalAttributes($storeId = null)
    {
        return $this->configHelper->getCategoryAdditionalAttributes($storeId);
    }

    /**
     * @param $storeId
     * @param null $categoryIds
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return CategoryCollection
     */
    public function getCategoryCollectionQuery($storeId, $categoryIds = null)
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $storeRootCategoryPath = sprintf('%d/%d/', $this->getRootCategoryId(), $store->getRootCategoryId());

        $unserializedCategorysAttrs = $this->getAdditionalAttributes($storeId);
        $additionalAttr = array_column($unserializedCategorysAttrs, 'attribute');

        $categories = $this->categoryCollectionFactory->create()
            ->distinct(true)
            ->addNameToResult()
            ->setStoreId($storeId)
            ->addUrlRewriteToResult()
            ->addAttributeToFilter('level', ['gt' => 1])
            ->addPathFilter($storeRootCategoryPath)
            ->addAttributeToSelect(array_merge(['name', 'is_active', 'include_in_menu', 'image'], $additionalAttr))
            ->addOrderField('entity_id');

        if ($categoryIds) {
            $categories->addAttributeToFilter('entity_id', ['in' => $categoryIds]);
        }

        $this->eventManager->dispatch(
            'algolia_after_categories_collection_build',
            ['store' => $storeId, 'collection' => $categories]
        );

        return $categories;
    }

    /**
     * @param Category $category
     * @param int $storeId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return bool
     *
     */
    public function canCategoryBeReindexed($category, $storeId)
    {
        if ($this->isCategoryActive($category, $storeId) === false) {
            throw new CategoryNotActiveException();
        }

        if ($this->configHelper->shouldIndexEmptyCategories($storeId) === false && $category->getProductCount() <= 0) {
            throw new CategoryEmptyException();
        }

        return true;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getAllAttributes()
    {
        if (isset($this->categoryAttributes)) {
            return $this->categoryAttributes;
        }

        $this->categoryAttributes = [];

        $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_category');

        $categoryAttributes = array_merge($allAttributes, ['product_count']);

        $excludedAttributes = [
            'all_children', 'available_sort_by', 'children', 'children_count', 'custom_apply_to_products',
            'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update',
            'custom_use_parent_settings', 'default_sort_by', 'display_mode', 'filter_price_range',
            'global_position', 'image', 'include_in_menu', 'is_active', 'is_always_include_in_menu', 'is_anchor',
            'landing_page', 'level', 'lower_cms_block', 'page_layout', 'path_in_store', 'position', 'small_image',
            'thumbnail', 'url_key', 'url_path','visible_in_menu',
        ];

        $categoryAttributes = array_diff($categoryAttributes, $excludedAttributes);

        foreach ($categoryAttributes as $attributeCode) {
            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            $attribute = $this->eavConfig->getAttribute('catalog_category', $attributeCode);
            $this->categoryAttributes[$attributeCode] = $attribute->getData('frontend_label');
        }

        return $this->categoryAttributes;
    }

    /**
     * @param MagentoCategory $category
     * @return array|mixed|null
     * @throws LocalizedException
     */
    public function getObject(Category $category)
    {
        return $this->categoryRecordBuilder->buildRecord($category);
    }

    /**
     * @return int|mixed
     * @throws LocalizedException
     */
    protected function getRootCategoryId()
    {
        if ($this->rootCategoryId !== -1) {
            return $this->rootCategoryId;
        }

        $collection = $this->categoryCollectionFactory->create()->addAttributeToFilter('parent_id', '0');

        /** @var \Magento\Catalog\Model\Category $rootCategory */
        $rootCategory = $collection->getFirstItem();

        $this->rootCategoryId = $rootCategory->getId();

        return $this->rootCategoryId;
    }

    /**
     * @param Category $category
     * @param int|null $storeId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return bool
     *
     */
    public function isCategoryActive($category, $storeId = null)
    {
        $pathIds = $category->getPathIds();
        array_shift($pathIds);

        foreach ($pathIds as $pathId) {
            $parent = $this->categoryRepository->get($pathId, $storeId);
            if ($parent && (bool) $parent->getIsActive() === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $categoryId
     * @param null $storeId
     *
     * @return string|null
     */
    public function getCategoryName($categoryId, $storeId = null)
    {
        return $this->categoryRecordBuilder->getCategoryName($categoryId, $storeId);
    }

    /**
     * @param $filterNotIncludedCategories
     * @param $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getCoreCategories($filterNotIncludedCategories = true, $storeId = null)
    {
        return $this->categoryRecordBuilder->getCoreCategories($filterNotIncludedCategories, $storeId);
    }
}
