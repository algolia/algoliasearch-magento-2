<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryExtensionFactory;
use Algolia\AlgoliaSearch\Api\CategoryVersionRepositoryInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionAttributeInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionAttributeInterfaceFactory;

class CategoryVersionPlugin {
    /** @var CategoryVersionRepositoryInterface */
    protected CategoryVersionRepositoryInterface $versionRepository;

    /** @var CategoryExtensionFactory */
    protected CategoryExtensionFactory $extensionFactory;

    /** @var CategoryVersionAttributeInterfaceFactory */
    protected CategoryVersionAttributeInterfaceFactory $versionAttributeFactory;

    /** @var ConfigHelper */
    protected ConfigHelper $config;

    public function __construct(
        CategoryVersionRepositoryInterface $versionRepository,
        CategoryExtensionFactory $extensionFactory,
        CategoryVersionAttributeInterfaceFactory $versionAttributeFactory,
        ConfigHelper $config
    ) {
        $this->versionRepository = $versionRepository;
        $this->extensionFactory = $extensionFactory;
        $this->versionAttributeFactory = $versionAttributeFactory;
        $this->config = $config;
    }

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryInterface $category
     * @param int $categoryId
     * @param int|null $storeId
     * @return CategoryInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterGet(CategoryRepositoryInterface $categoryRepository, CategoryInterface $category, int $categoryId, int $storeId = null): CategoryInterface {
        if (!$this->config->isCategoryVersionTrackingEnabled()) return $category;
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug("Category called for store id $storeId and category id $categoryId");

        $extensionAttributes = $category->getExtensionAttributes() ?? $this->extensionFactory->create();
        $versionAttribute = $extensionAttributes->getAlgoliaCategoryVersions() ?? $this->versionAttributeFactory->create()->load($categoryId, $storeId);
        $extensionAttributes->setAlgoliaCategoryVersions($versionAttribute);
        $category->setExtensionAttributes($extensionAttributes);
        return $category;
    }

    // Unsupported in admin by core
    public function afterSave(CategoryRepositoryInterface $categoryRepository, CategoryInterface $result, CategoryInterface $category): CategoryInterface {
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug('Category save called');
        return $result;
    }
}
