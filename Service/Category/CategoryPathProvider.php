<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Category;

class CategoryPathProvider
{
    public function __construct(
        protected ConfigHelper  $config,
        protected RecordBuilder $recordBuilder,
    ) {}

    /**
     * Returns category path details useful for faceting / filtering.
     *
     * @return array{
     *   path: string,            // Full category path delimited by the category separator
     *   level: int,              // Depth in category tree (root = 0)
     *   parentCategory: string   // Display name of the parent category
     * }
     */
    public function getCategoryPathDetails(Category $category, ?int $storeId = null): array
    {
        $level = -1;
        $path = '';
        $parentCategory = '';
        $previousCategoryName = '';

        foreach ($category->getPathIds() as $treeCategoryId) {
            $categoryName = $this->recordBuilder->getCategoryName($treeCategoryId, $storeId);

            if ($categoryName === null) {
                continue;
            }

            if ($path !== '') {
                $path .= $this->config->getCategorySeparator($storeId);
                $parentCategory = $previousCategoryName;
            }

            $path .= $categoryName;
            $previousCategoryName = $categoryName;
            $level++;
        }

        return [
            'path' => $path,
            'level' => $level,
            'parentCategory' => $parentCategory,
        ];
    }

    public function getCategoryPageId(Category $category, ?int $storeId = null): string
    {
        return $this->getCategoryPathDetails($category, $storeId)['path'];
    }
}
