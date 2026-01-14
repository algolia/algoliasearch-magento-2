<?php

use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\MultiStoreProductsTest;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

$website = Bootstrap::getObjectManager()->create(\Magento\Store\Model\Website::class);
/** @var $website \Magento\Store\Model\Website */
if (!$website->load('test', 'code')->getId()) {
    $website->setData(['code' => 'test', 'name' => 'Test Website', 'default_group_id' => '1', 'is_default' => '0']);
    $website->save();
}
$websiteId = $website->getId();
$store = Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
if (!$store->load('fixture_second_store', 'code')->getId()) {
    $groupId = Bootstrap::getObjectManager()->get(
        StoreManagerInterface::class
    )->getWebsite()->getDefaultGroupId();
    $store->setCode(
        'fixture_second_store'
    )->setWebsiteId(
        $websiteId
    )->setGroupId(
        $groupId
    )->setName(
        'Fixture Second Store'
    )->setSortOrder(
        10
    )->setIsActive(
        1
    );
    $store->save();
}

$store = Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
if (!$store->load('fixture_third_store', 'code')->getId()) {
    $groupId = Bootstrap::getObjectManager()->get(
        StoreManagerInterface::class
    )->getWebsite()->getDefaultGroupId();
    $store->setCode(
        'fixture_third_store'
    )->setWebsiteId(
        $websiteId
    )->setGroupId(
        $groupId
    )->setName(
        'Fixture Third Store'
    )->setSortOrder(
        11
    )->setIsActive(
        1
    );
    $store->save();
}

$objectManager = Bootstrap::getObjectManager();
$websiteRepository = $objectManager->get(WebsiteRepositoryInterface::class);
$websites = $websiteRepository->getList();
$websiteIds = [];
foreach ($websites as $website) {
    $websiteIds[] = $website->getId();
}

$configManager = $objectManager->get(\Magento\Framework\App\Config\MutableScopeConfigInterface::class);
// Temporarily disable indexing during product assignment to stores
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 0, 'store', 'admin');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 0, 'store', 'default');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 0, 'store', 'fixture_second_store');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 0, 'store', 'fixture_third_store');

$productSkus = MultiStoreProductsTest::SKUS;
$productRepository = Bootstrap::getObjectManager()
    ->get(ProductRepositoryInterface::class);

foreach ($productSkus as $sku) {
    $product = $productRepository->get($sku);
    $product->setWebsiteIds($websiteIds);
    $productRepository->save($product);
}

$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 1, 'store', 'admin');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 1, 'store', 'default');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 1, 'store', 'fixture_second_store');
$configManager->setValue('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', 1, 'store', 'fixture_third_store');

// Set base urls
$configManager->setValue('web/secure/base_url', 'http://default.test/', 'store', 'default');
$configManager->setValue('web/unsecure/base_url', 'http://default.test/', 'store', 'default');
$configManager->setValue('web/secure/base_link_url', 'http://default.test/', 'store', 'default');
$configManager->setValue('web/unsecure/base_link_url', 'http://default.test/', 'store', 'default');

$configManager->setValue('web/secure/base_url', 'http://fixture_second_store.test/', 'store', 'fixture_second_store');
$configManager->setValue('web/unsecure/base_url', 'http://fixture_second_store.test/', 'store', 'fixture_second_store');
$configManager->setValue('web/secure/base_link_url', 'http://fixture_second_store.test/', 'store', 'fixture_second_store');
$configManager->setValue('web/unsecure/base_link_url', 'http://fixture_second_store.test/', 'store', 'fixture_second_store');

$configManager->setValue('web/secure/base_url', 'http://fixture_third_store.test/', 'store', 'fixture_third_store');
$configManager->setValue('web/unsecure/base_url','http://fixture_third_store.test/', 'store', 'fixture_third_store');
$configManager->setValue('web/secure/base_link_url', 'http://fixture_third_store.test/', 'store', 'fixture_third_store');
$configManager->setValue('web/unsecure/base_link_url','http://fixture_third_store.test/', 'store', 'fixture_third_store');

/* Refresh CatalogSearch index */
/** @var IndexerRegistry $indexerRegistry */
$indexerRegistry = Bootstrap::getObjectManager()
    ->create(IndexerRegistry::class);
$indexerRegistry->get(Fulltext::INDEXER_ID)->reindexAll();
