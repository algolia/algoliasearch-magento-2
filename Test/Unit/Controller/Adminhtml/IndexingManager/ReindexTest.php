<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ReindexTest extends TestCase
{
    protected ?ReindexTestable $reindexController = null;

    protected ?Context $context = null;
    protected ?RequestInterface $request = null;
    protected ?ManagerInterface $messageManager = null;
    protected ?ResultFactory $resultFactory = null;

    protected ?StoreManagerInterface $storeManager = null;
    protected ?StoreNameFetcher $storeNameFetcher = null;
    protected ?IndexNameFetcher $indexNameFetcher = null;
    protected ?ConfigHelper $configHelper = null;
    protected ?ProductBatchQueueProcessor $productBatchQueueProcessor = null;
    protected ?CategoryBatchQueueProcessor $categoryBatchQueueProcessor = null;
    protected ?PageBatchQueueProcessor $pageBatchQueueProcessor = null;

    protected ?array $stores = ["1" => "foo", "2" => "bar"];

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $resultInstance = $this->createMock(Redirect::class);
        $resultInstance->method('setPath')->willReturn('');
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($resultInstance);

        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);
        $this->context->method('getResultFactory')->willReturn($this->resultFactory);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStores')->willReturn($this->stores);

        $this->storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $this->indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $this->categoryBatchQueueProcessor = $this->createMock(CategoryBatchQueueProcessor::class);
        $this->pageBatchQueueProcessor = $this->createMock(PageBatchQueueProcessor::class);

        $this->reindexController = new ReindexTestable(
            $this->context,
            $this->storeManager,
            $this->storeNameFetcher,
            $this->indexNameFetcher,
            $this->configHelper,
            $this->productBatchQueueProcessor,
            $this->categoryBatchQueueProcessor,
            $this->pageBatchQueueProcessor
        );
    }

    public function testExecuteFullIndexingAllEntitiesAllStores()
    {
        $this->request
            ->expects($this->once())
            ->method('getParams')
            ->willReturn([
                "store_id" => null,
                "entity" => "all",
            ]);

        $this->productBatchQueueProcessor
            ->expects($this->exactly(2))
            ->method('processBatch');

        $this->categoryBatchQueueProcessor
            ->expects($this->exactly(2))
            ->method('processBatch');

        $this->pageBatchQueueProcessor
            ->expects($this->exactly(2))
            ->method('processBatch');

        $this->reindexController->execute();
    }

    public function testExecuteFullIndexingPagesAllStores()
    {
        $this->request
            ->expects($this->once())
            ->method('getParams')
            ->willReturn([
                "store_id" => null,
                "entity" => "pages",
            ]);

        $this->productBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->categoryBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->pageBatchQueueProcessor
            ->expects($this->exactly(2))
            ->method('processBatch');

        $this->reindexController->execute();
    }

    public function testExecuteFullIndexingProductsOneStore()
    {
        $this->request
            ->expects($this->once())
            ->method('getParams')
            ->willReturn([
                "store_id" => "1",
                "entity" => "products",
            ]);

        $this->productBatchQueueProcessor
            ->expects($this->once())
            ->method('processBatch');

        $this->categoryBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->pageBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->reindexController->execute();
    }

    public function testExecuteProductsMassAction()
    {
        $selectedProducts = [2, 3, 4];

        $this->request
            ->expects($this->once())
            ->method('getParams')
            ->willReturn([
                "store_id" => null,
                "namespace" => "product_listing",
                "selected" => $selectedProducts,
            ]);

        $this->productBatchQueueProcessor
            ->expects($this->exactly(2))
            ->method('processBatch')
            ->with(1 || 2, $selectedProducts);

        $this->categoryBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->pageBatchQueueProcessor
            ->expects($this->never())
            ->method('processBatch');

        $this->reindexController->execute();
    }

    /**
     * @dataProvider entityParamsProvider
     */
    public function testEntityToIndex($params, $result)
    {
        $this->assertEquals($result, $this->reindexController->defineEntitiesToIndex($params));
    }

    public function entityParamsProvider()
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

    /**
     * @dataProvider redirectParamsProvider
     */
    public function testRedirectPath($params, $result)
    {
        $this->assertEquals($result, $this->reindexController->defineRedirectPath($params));
    }

    public function redirectParamsProvider()
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
