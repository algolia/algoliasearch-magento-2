<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Api\RecordBuilder\RecordBuilderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category as MagentoCategory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Url;
use Magento\Store\Model\Store;

class RecordBuilder implements RecordBuilderInterface
{
    protected $categoryNames;
    protected $idColumn;
    protected $coreCategories;

    public function __construct(
        protected ManagerInterface          $eventManager,
        protected ConfigHelper              $configHelper,
        protected CategoryResource          $categoryResource,
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected ResourceConnection        $resourceConnection,
        protected Manager                   $moduleManager,
    ) {}

    public function buildRecord(DataObject $entity): array
    {
        if (!$entity instanceof MagentoCategory) {
            throw new AlgoliaException('Object must be a Category model');
        }

        $category = $entity;

        /** @var Collection $productCollection */
        $productCollection = $category->getProductCollection();
        $category->setProductCount($productCollection->getSize());

        $transport = new DataObject();
        $this->eventManager->dispatch(
            'algolia_category_index_before',
            ['category' => $category, 'custom_data' => $transport]
        );
        $customData = $transport->getData();

        $storeId = $category->getStoreId();

        /** @var Url $urlInstance */
        $urlInstance = $category->getUrlInstance();
        $urlInstance->setData('store', $storeId);

        $path = '';
        foreach ($category->getPathIds() as $categoryId) {
            if ($path !== '') {
                $path .= ' / ';
            }

            $path .= $this->getCategoryName($categoryId, $storeId);
        }

        $imageUrl = null;

        try {
            $imageUrl = $category->getImageUrl();
        } catch (\Exception $e) {
            /* no image, no default: not fatal */
        }

        $data = [
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID => $category->getId(),
            'name'                                  => $category->getName(),
            'path'                                  => $path,
            'level'                                 => $category->getLevel(),
            'url'                                   => $this->getUrl($category),
            'include_in_menu'                       => $category->getIncludeInMenu(),
            '_tags'                                 => ['category'],
            'popularity'                            => 1,
            'product_count'                         => $category->getProductCount(),
        ];

        if (!empty($imageUrl)) {
            $data['image_url'] = $imageUrl;
        }

        foreach ($this->configHelper->getCategoryAdditionalAttributes($storeId) as $attribute) {
            $value = $category->getData($attribute['attribute']);

            /** @var CategoryResource $resource */
            $resource = $category->getResource();

            $attributeResource = $resource->getAttribute($attribute['attribute']);
            if ($attributeResource) {
                $value = $attributeResource->getFrontend()->getValue($category);
            }

            if (isset($data[$attribute['attribute']])) {
                $value = $data[$attribute['attribute']];
            }

            if ($value) {
                $data[$attribute['attribute']] = $value;
            }
        }

        $data = array_merge($data, $customData);

        $transport = new DataObject($data);
        $this->eventManager->dispatch(
            'algolia_after_create_category_object',
            ['category' => $category, 'categoryObject' => $transport]
        );

        return $transport->getData();
    }

    /**
     * @param MagentoCategory $category
     * @return array|string|string[]
     */
    protected function getUrl(Category $category)
    {
        $categoryUrl = $category->getUrl();

        if ($this->configHelper->useSecureUrlsInFrontend($category->getStoreId()) === false) {
            return $categoryUrl;
        }

        $unsecureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => false]);
        $secureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => true]);

        if (mb_strpos($categoryUrl, $unsecureBaseUrl) === 0) {
            return substr_replace($categoryUrl, $secureBaseUrl, 0, mb_strlen($unsecureBaseUrl));
        }

        return $categoryUrl;
    }

    /**
     * @param int $categoryId
     * @param null $storeId
     *
     * @return string|null
     */
    public function getCategoryName($categoryId, $storeId = null)
    {
        if ($categoryId instanceof MagentoCategory) {
            $categoryId = $categoryId->getId();
        }

        if ($storeId instanceof Store) {
            $storeId = $storeId->getId();
        }

        $categoryId = (int) $categoryId;
        $storeId = (int) $storeId;
        if (!isset($this->categoryNames)) {
            $this->categoryNames = [];

            $categoryModel = $this->categoryResource;

            if ($attribute = $categoryModel->getAttribute('name')) {
                $columnId = $this->getCorrectIdColumn();
                $expression = new \Zend_Db_Expr("CONCAT(backend.store_id, '-', backend." . $columnId . ')');

                $connection = $this->resourceConnection->getConnection();
                $select = $connection->select()
                    ->from(
                        ['backend' => $attribute->getBackendTable()],
                        [$expression, 'backend.value']
                    )
                    ->join(
                        ['category' => $categoryModel->getTable('catalog_category_entity')],
                        'backend.' . $columnId . ' = category.' . $columnId,
                        []
                    )
                    ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                    ->where('category.level > ?', 1);

                $this->categoryNames = $connection->fetchPairs($select);
            }
        }

        $categoryName = null;

        $categoryKeyId = $this->getCategoryKeyId($categoryId, $storeId);

        if ($categoryKeyId === null) {
            return $categoryName;
        }

        $key = $storeId . '-' . $categoryKeyId;

        if (isset($this->categoryNames[$key])) {
            // Check whether the category name is present for the specified store
            $categoryName = (string) $this->categoryNames[$key];
        } elseif ($storeId !== 0) {
            // Check whether the category name is present for the default store
            $key = '0-' . $categoryKeyId;
            if (isset($this->categoryNames[$key])) {
                $categoryName = (string) $this->categoryNames[$key];
            }
        }

        return $categoryName;
    }

    /**
     * @param $categoryId
     * @param $storeId
     * @return mixed|null
     */
    protected function getCategoryKeyId($categoryId, $storeId = null)
    {
        $categoryKeyId = $categoryId;

        if ($this->getCorrectIdColumn() === 'row_id') {
            $category = $this->getCategoryById($categoryId, $storeId);
            return $category ? $category->getRowId() : null;
        }

        return $categoryKeyId;
    }

    /**
     * @param $categoryId
     * @param $storeId
     * @return mixed|null
     * @throws LocalizedException
     */
    protected function getCategoryById($categoryId, $storeId = null)
    {
        $categories = $this->getCoreCategories(false, $storeId);

        return $categories[$categoryId] ?? null;
    }

    /**
     * @param $filterNotIncludedCategories
     * @param $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getCoreCategories($filterNotIncludedCategories = true, $storeId = null)
    {
        // Cache category look up by store scope
        $key = ($filterNotIncludedCategories ? 'filtered' : 'non_filtered') . "-$storeId";

        if (isset($this->coreCategories[$key])) {
            return $this->coreCategories[$key];
        }

        $collection = $this->categoryCollectionFactory->create()
            ->distinct(true)
            ->setStoreId($storeId)
            ->addNameToResult()
            ->addIsActiveFilter()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('level', ['gt' => 1]);

        if ($filterNotIncludedCategories) {
            $collection->addAttributeToFilter('include_in_menu', '1');
        }

        $this->coreCategories[$key] = [];

        /** @var MagentoCategory $category */
        foreach ($collection as $category) {
            $this->coreCategories[$key][$category->getId()] = $category;
        }

        return $this->coreCategories[$key];
    }

    /**
     * @return string
     */
    protected function getCorrectIdColumn()
    {
        if (isset($this->idColumn)) {
            return $this->idColumn;
        }

        $this->idColumn = 'entity_id';

        $edition = $this->configHelper->getMagentoEdition();
        $version = $this->configHelper->getMagentoVersion();

        if ($edition !== 'Community' && version_compare($version, '2.1.0', '>=') && $this->moduleManager->isEnabled('Magento_Staging')) {
            $this->idColumn = 'row_id';
        }

        return $this->idColumn;
    }
}
