<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Page;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\MultiStoreTestCase;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiStorePagesTest extends MultiStoreTestCase
{
    /** @var PageRepositoryInterface */
    protected $pageRepository;

    /**  @var CollectionFactory */
    private $pageCollectionFactory;

    /** @var PageBatchQueueProcessor */
    protected $pageBatchQueueProcessor;

    const HOME_PAGE_ID = 2;

    public function setUp():void
    {
        parent::setUp();

        $this->pageRepository = $this->objectManager->get(PageRepositoryInterface::class);
        $this->pageCollectionFactory = $this->objectManager->get(CollectionFactory::class);

        $this->pageBatchQueueProcessor = $this->objectManager->get(PageBatchQueueProcessor::class);
        $this->reindexToAllStores($this->pageBatchQueueProcessor);
    }

    /***
     * @magentoDbIsolation disabled
     *
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     */
    public function testMultiStorePageIndices()
    {
        // Check that every store has the right number of pages
        foreach ($this->storeManager->getStores() as $store) {
            $this->assertNbOfRecordsPerStore(
                $store->getCode(),
                'pages',
                $store->getCode() === 'fixture_second_store' ? // we excluded 2 pages on setupStore()
                    $this->assertValues->expectedExcludePages :
                    $this->assertValues->expectedPages,
                $store->getId()
            );
        }

        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        try {
            $homePage = $this->loadPage(self::HOME_PAGE_ID);
        } catch (\Exception) {
            $this->markTestIncomplete('Page could not be found.');
        }

        // Setting the page only for default store
        $homePage->setStores([$defaultStore->getId()]);
        $this->pageRepository->save($homePage);

        $this->reindexToAllStores($this->pageBatchQueueProcessor, [self::HOME_PAGE_ID]);

        $this->assertNbOfRecordsPerStore(
            $defaultStore->getCode(),
            'pages',
            $this->assertValues->expectedPages,
            $defaultStore->getId()
        );

        $this->assertNbOfRecordsPerStore(
            $fixtureSecondStore->getCode(),
            'pages',
            $this->assertValues->expectedExcludePages - 1,
            $fixtureSecondStore->getId()
        );
    }

    /**
     * Loads page by id.
     *
     * @param int $pageId
     *
     * @return PageInterface
     * @throws LocalizedException
     */
    private function loadPage(int $pageId): PageInterface
    {
        return $this->pageRepository->getById($pageId);
    }

    protected function resetPage(PageInterface $page): void
    {
        $page->setStores([0]);
        $this->pageRepository->save($page);
    }

    /**
     * @param StoreInterface $store
     * @param bool $enableInstantSearch
     *
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setupStore(StoreInterface $store, bool $enableInstantSearch = false): void
    {
        // Exclude 2 pages on second store
        $excludedPages = $store->getCode() === 'fixture_second_store' ?
            [['attribute' => 'no-route'], ['attribute' => 'enable-cookies']]:
            [];

        $this->setConfig(
            ConfigHelper::EXCLUDED_PAGES,
            $this->getSerializer()->serialize($excludedPages),
            $store->getCode()
        );

        parent::setupStore($store, $enableInstantSearch);
    }

    public function tearDown(): void
    {
        // Restore page in case DB is not cleaned up
        $homePage = $this->loadPage(self::HOME_PAGE_ID);
        $this->resetPage($homePage);

        parent::tearDown();
    }
}
