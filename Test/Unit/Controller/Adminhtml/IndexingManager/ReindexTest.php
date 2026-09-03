<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager\Reindex;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class ReindexTest extends TestCase
{
    protected function createObjectToTest(
        ?RequestInterface $request = null,
        ?ManagerInterface $messageManager = null,
        ?ResultFactory $resultFactory = null,
        ?StoreManagerInterface $storeManager = null,
        ?StoreNameFetcher $storeNameFetcher = null,
        ?IndexNameFetcher $indexNameFetcher = null,
        ?ConfigHelper $configHelper = null,
        ?ProductBatchQueueProcessor $productBatchQueueProcessor = null,
        ?CategoryBatchQueueProcessor $categoryBatchQueueProcessor = null,
        ?PageBatchQueueProcessor $pageBatchQueueProcessor = null,
    ): Reindex {
        if ($resultFactory === null) {
            $resultInstance = $this->createStub(Redirect::class);
            $resultInstance->method('setPath')->willReturnSelf();

            $resultFactory = $this->createMock(ResultFactory::class);
            $resultFactory->method('create')->with(ResultFactory::TYPE_REDIRECT)->willReturn($resultInstance);
        }

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request ?? $this->createStub(RequestInterface::class));
        $context->method('getMessageManager')->willReturn($messageManager ?? $this->createStub(ManagerInterface::class));
        $context->method('getResultFactory')->willReturn($resultFactory);

        return new Reindex(
            $context,
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $indexNameFetcher ?? $this->createStub(IndexNameFetcher::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $productBatchQueueProcessor ?? $this->createStub(ProductBatchQueueProcessor::class),
            $categoryBatchQueueProcessor ?? $this->createStub(CategoryBatchQueueProcessor::class),
            $pageBatchQueueProcessor ?? $this->createStub(PageBatchQueueProcessor::class),
        );
    }

    public function testExecuteFullIndexingAllEntitiesAllStores()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParams')->willReturn([
            'store_id' => null,
            'entity' => 'all',
        ]);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn(['1' => 'foo', '2' => 'bar']);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->exactly(2))->method('processBatch');

        $categoryBatchQueueProcessor = $this->createMock(CategoryBatchQueueProcessor::class);
        $categoryBatchQueueProcessor->expects($this->exactly(2))->method('processBatch');

        $pageBatchQueueProcessor = $this->createMock(PageBatchQueueProcessor::class);
        $pageBatchQueueProcessor->expects($this->exactly(2))->method('processBatch');

        $controller = $this->createObjectToTest(
            request: $request,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
            categoryBatchQueueProcessor: $categoryBatchQueueProcessor,
            pageBatchQueueProcessor: $pageBatchQueueProcessor,
        );

        $controller->execute();
    }

    public function testExecuteFullIndexingPagesAllStores()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParams')->willReturn([
            'store_id' => null,
            'entity' => 'pages',
        ]);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn(['1' => 'foo', '2' => 'bar']);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $categoryBatchQueueProcessor = $this->createMock(CategoryBatchQueueProcessor::class);
        $categoryBatchQueueProcessor->expects($this->never())->method('processBatch');

        $pageBatchQueueProcessor = $this->createMock(PageBatchQueueProcessor::class);
        $pageBatchQueueProcessor->expects($this->exactly(2))->method('processBatch');

        $controller = $this->createObjectToTest(
            request: $request,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
            categoryBatchQueueProcessor: $categoryBatchQueueProcessor,
            pageBatchQueueProcessor: $pageBatchQueueProcessor,
        );

        $controller->execute();
    }

    public function testExecuteFullIndexingProductsOneStore()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParams')->willReturn([
            'store_id' => '1',
            'entity' => 'products',
        ]);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->once())->method('processBatch');

        $categoryBatchQueueProcessor = $this->createMock(CategoryBatchQueueProcessor::class);
        $categoryBatchQueueProcessor->expects($this->never())->method('processBatch');

        $pageBatchQueueProcessor = $this->createMock(PageBatchQueueProcessor::class);
        $pageBatchQueueProcessor->expects($this->never())->method('processBatch');

        $controller = $this->createObjectToTest(
            request: $request,
            productBatchQueueProcessor: $productBatchQueueProcessor,
            categoryBatchQueueProcessor: $categoryBatchQueueProcessor,
            pageBatchQueueProcessor: $pageBatchQueueProcessor,
        );

        $controller->execute();
    }

    public function testExecuteProductsMassAction()
    {
        $selectedProducts = [2, 3, 4];

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParams')->willReturn([
            'store_id' => null,
            'namespace' => 'product_listing',
            'selected' => $selectedProducts,
        ]);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn(['1' => 'foo', '2' => 'bar']);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->exactly(2))
            ->method('processBatch')
            ->with(1 || 2, $selectedProducts);

        $categoryBatchQueueProcessor = $this->createMock(CategoryBatchQueueProcessor::class);
        $categoryBatchQueueProcessor->expects($this->never())->method('processBatch');

        $pageBatchQueueProcessor = $this->createMock(PageBatchQueueProcessor::class);
        $pageBatchQueueProcessor->expects($this->never())->method('processBatch');

        $controller = $this->createObjectToTest(
            request: $request,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
            categoryBatchQueueProcessor: $categoryBatchQueueProcessor,
            pageBatchQueueProcessor: $pageBatchQueueProcessor,
        );

        $controller->execute();
    }

    #[DataProvider('entityParamsProvider')]
    public function testEntityToIndex($params, $result)
    {
        $controller = $this->createObjectToTest();

        $this->assertEquals($result, $this->invokeMethod($controller, 'defineEntitiesToIndex', [$params]));
    }

    public static function entityParamsProvider(): array
    {
        return [
            [
                'params' => [],
                'result' => [],
            ],
            [
                'params' => ['entity' => 'all'],
                'result' => ['products', 'categories', 'pages'],
            ],
            [
                'params' => ['entity' => 'categories'],
                'result' => ['categories'],
            ],
            [
                'params' => ['namespace' => 'product_listing'],
                'result' => ['products'],
            ],
            [
                'params' => ['namespace' => 'cms_page_listing'],
                'result' => ['pages'],
            ],
        ];
    }

    #[DataProvider('redirectParamsProvider')]
    public function testRedirectPath($params, $result)
    {
        $controller = $this->createObjectToTest();

        $this->assertEquals($result, $this->invokeMethod($controller, 'defineRedirectPath', [$params]));
    }

    public static function redirectParamsProvider(): array
    {
        return [
            [
                'params' => [],
                'result' => '*/*/',
            ],
            [
                'params' => ['foo' => 'bar'],
                'result' => '*/*/',
            ],
            [
                'params' => ['redirect' => 'my/custom/url'],
                'result' => 'my/custom/url',
            ],
            [
                'params' => ['namespace' => 'product_listing'],
                'result' => 'catalog/product/index',
            ],
            [
                'params' => ['namespace' => 'cms_page_listing'],
                'result' => 'cms/page/index',
            ],
        ];
    }
}
