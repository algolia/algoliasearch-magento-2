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
use PHPUnit\Framework\Attributes\DataProvider;

class AddAlgoliaAssetsObserverTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?RenderingManager $renderingManager = null,
        ?StoreManagerInterface $storeManager = null,
        ?Http $request = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
    ): AddAlgoliaAssetsObserver {
        return new AddAlgoliaAssetsObserver(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $renderingManager ?? $this->createStub(RenderingManager::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $request ?? $this->createStub(Http::class),
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
        );
    }

    private function createStoreStub(int $storeId = 1): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    private function createObserverStub(): Observer
    {
        $layout = $this->createStub(Layout::class);
        $observer = $this->createStub(Observer::class);
        $observer->method('getData')->willReturn($layout);

        return $observer;
    }

    public function testSwaggerActionReturnsEarly(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn('swagger_index_index');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->never())->method('isEnabledFrontEnd');

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->never())->method('checkCredentials');

        $renderingManager = $this->createMock(RenderingManager::class);
        $renderingManager->expects($this->never())->method('handleFrontendAssets');
        $renderingManager->expects($this->never())->method('handleBackendRendering');

        $observer = $this->createObjectToTest(
            configHelper: $configHelper,
            renderingManager: $renderingManager,
            storeManager: $storeManager,
            request: $request,
            credentialsManager: $credentialsManager,
        );

        $observer->execute($this->createObserverStub());
    }

    #[DataProvider('executeConditionsProvider')]
    public function testExecuteConditions(
        bool $isFrontendEnabled,
        bool $areCredentialsValid,
        bool $expectRenderingManagerCalled
    ): void {
        $storeId = 1;
        $actionName = 'catalog_category_view';

        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn($actionName);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createStoreStub($storeId));

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isEnabledFrontEnd')->willReturn($isFrontendEnabled);

        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentials')->willReturn($areCredentialsValid);

        $layout = $this->createStub(Layout::class);
        $observerArg = $this->createStub(Observer::class);
        $observerArg->method('getData')->willReturn($layout);

        $renderingManager = $this->createMock(RenderingManager::class);
        if ($expectRenderingManagerCalled) {
            $renderingManager->expects($this->once())
                ->method('handleFrontendAssets')
                ->with($layout, $storeId);
            $renderingManager->expects($this->once())
                ->method('handleBackendRendering')
                ->with($layout, $actionName, $storeId);
        } else {
            $renderingManager->expects($this->never())->method('handleFrontendAssets');
            $renderingManager->expects($this->never())->method('handleBackendRendering');
        }

        $observer = $this->createObjectToTest(
            configHelper: $configHelper,
            renderingManager: $renderingManager,
            storeManager: $storeManager,
            request: $request,
            credentialsManager: $credentialsManager,
        );

        $observer->execute($observerArg);
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
