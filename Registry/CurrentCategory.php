<?php

namespace Algolia\AlgoliaSearch\Registry;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;

class CurrentCategory
{
    private CategoryInterface $category;
    private CategoryRepositoryInterface $categoryRepository;
    private CategoryInterfaceFactory $categoryFactory;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryInterfaceFactory $categoryFactory
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->categoryFactory = $categoryFactory;
    }

    public function set(CategoryInterface $category): void {
        $this->category = $category;
    }

    public function get(): CategoryInterface {
        return $this->category ?? $this->categoryFactory->create();
    }
}
