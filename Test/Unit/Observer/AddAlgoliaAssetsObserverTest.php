<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Observer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Observer\AddAlgoliaAssetsObserver;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\RenderingManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddAlgoliaAssetsObserverTest extends TestCase
{
    protected ?AddAlgoliaAssetsObserver $observer;
    protected ?ConfigHelper $configHelper;
    protected ?RenderingManager $renderingManager;
    protected ?StoreManagerInterface $storeManager;
    protected ?Http $request;
    protected ?AlgoliaCredentialsManager $credentialsManager;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->renderingManager = $this->createMock(RenderingManager::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(Http::class);
        $this->credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);

        $this->observer = new AddAlgoliaAssetsObserver(
            $this->configHelper,
            $this->renderingManager,
            $this->storeManager,
            $this->request,
            $this->credentialsManager
        );
    }

    protected function createMockStore(int $storeId = 1): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        return $store;
    }

    protected function createMockObserver(): Observer
    {
        $layout = $this->createMock(Layout::class);
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('layout')->willReturn($layout);
        return $observer;
    }

    public function testSwaggerActionReturnsEarly(): void
    {
        $this->request->method('getFullActionName')->willReturn('swagger_index_index');

        $this->storeManager->expects($this->never())->method('getStore');
        $this->configHelper->expects($this->never())->method('isEnabledFrontEnd');
        $this->credentialsManager->expects($this->never())->method('checkCredentials');
        $this->renderingManager->expects($this->never())->method('handleFrontendAssets');
        $this->renderingManager->expects($this->never())->method('handleBackendRendering');

        $this->observer->execute($this->createMockObserver());
    }

    /**
     * @dataProvider executeConditionsProvider
     */
    public function testExecuteConditions(
        bool $isFrontendEnabled,
        bool $areCredentialsValid,
        bool $expectRenderingManagerCalled
    ): void {
        $storeId = 1;
        $actionName = 'catalog_category_view';

        $this->request->method('getFullActionName')->willReturn($actionName);
        $this->storeManager->method('getStore')->willReturn($this->createMockStore($storeId));
        $this->configHelper->method('isEnabledFrontEnd')->with($storeId)->willReturn($isFrontendEnabled);
        $this->credentialsManager->method('checkCredentials')->with($storeId)->willReturn($areCredentialsValid);

        $layout = $this->createMock(Layout::class);
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('layout')->willReturn($layout);

        if ($expectRenderingManagerCalled) {
            $this->renderingManager->expects($this->once())
                ->method('handleFrontendAssets')
                ->with($layout, $storeId);
            $this->renderingManager->expects($this->once())
                ->method('handleBackendRendering')
                ->with($layout, $actionName, $storeId);
        } else {
            $this->renderingManager->expects($this->never())->method('handleFrontendAssets');
            $this->renderingManager->expects($this->never())->method('handleBackendRendering');
        }

        $this->observer->execute($observer);
    }

    public static function executeConditionsProvider(): array
    {
        return [
            'Frontend enabled and credentials valid' => [
                'isFrontendEnabled' => true,
                'areCredentialsValid' => true,
                'expectRenderingManagerCalled' => true,
            ],
            'Frontend disabled' => [
                'isFrontendEnabled' => false,
                'areCredentialsValid' => true,
                'expectRenderingManagerCalled' => false,
            ],
            'Credentials invalid' => [
                'isFrontendEnabled' => true,
                'areCredentialsValid' => false,
                'expectRenderingManagerCalled' => false,
            ],
            'Frontend disabled and credentials invalid' => [
                'isFrontendEnabled' => false,
                'areCredentialsValid' => false,
                'expectRenderingManagerCalled' => false,
            ],
        ];
    }
}

