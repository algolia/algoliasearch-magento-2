<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CategoryPathProvider
{
    public function __construct(
        protected ConfigHelper              $config,
        protected CategoryRepositoryInterface   $categoryRepository,
        protected CategoryCollectionFactory $categoryCollectionFactory,
    ) {}

    /**
     * Returns category path details useful for faceting / filtering.
     *
     * @return array{
     *   path: string,            // Full category path delimited by the category separator
     *   level: int,              // Depth in category tree (root = 0)
     *   parentCategory: string   // Display name of the parent category
     * }
     * @throws LocalizedException
     */
    public function getCategoryPathDetails(Category $category, ?int $storeId = null, ?string $altSeparator = null): array
    {
        $level = '';
        $path = '';
        $parentCategory = '';
        $previousCategoryName = '';

        $pathIds = $category->getPathIds();
        $categoryNameMap = $this->getCategoryNamesForPath($pathIds);

        $separator = $altSeparator ?? $this->config->getCategorySeparator($storeId);

        foreach ($pathIds as $treeCategoryId) {
            $categoryName = $categoryNameMap[$treeCategoryId] ?? null;

            if ($categoryName === null) {
                continue;
            }

            if ($level === '') {
                $level = -1;
            }

            if ($path !== '') {
                $path .= $separator;
                $parentCategory = $previousCategoryName;
            }

            $path .= $categoryName;
            $previousCategoryName = $categoryName;
            $level++;
        }

        return [
            'path'           => $path,
            'level'          => $level,
            'parentCategory' => $parentCategory,
        ];
    }

    /**
     * @return array<string, string>
     *     e.g.[ "11" => "Men", "12" => "Tops", "15" => 'Hoodies & Sweatshirts" ]
     * @throws LocalizedException
     */
    protected function getCategoryNamesForPath(array $categoryIds, ?int $storeId = null): array
    {
        $collection = $this->categoryCollectionFactory->create();

        if ($storeId) {
            $collection->setStoreId($storeId);
        }

        $collection
            ->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $categoryIds])
            ->addFieldToFilter('level', ['gt' => 1]);

        $names = [];
        foreach ($collection as $category) {
            $names[$category->getId()] = $category->getName();
        }

        return $names;
    }

    public function getCategoryPageId(Category|int $category, ?int $storeId = null, ?string $altSeparator = null): string
    {
        if (is_numeric($category)) { // normalize to Category objct
            $category = $this->categoryRepository->get($category);
            if (!$category instanceof Category) {
                return '';
            }
        }

        return $this->getCategoryPathDetails($category, $storeId, $altSeparator)['path'];
    }
}
