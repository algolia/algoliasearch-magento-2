<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Reindex;

use Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\AbstractReindexAllButton;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractReindexAllButtonTest extends TestCase
{
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(Context&MockObject) $context = null;
    protected null|(UrlInterface&MockObject) $urlBuilder = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->context = $this->createMock(Context::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->urlBuilder->method('getUrl')
            ->with('algolia_algoliasearch/indexingmanager/reindex')
            ->willReturn('http://example.com/reindex');

        $this->context->method('getUrlBuilder')->willReturn($this->urlBuilder);
    }

    private function makeButton(string $entity, string $redirectPath): AbstractReindexAllButton
    {
        return new class($this->context, $this->configHelper, $entity, $redirectPath) extends AbstractReindexAllButton {
            public function __construct(
                Context $context,
                ConfigHelper $configHelper,
                private string $entityValue,
                private string $redirectPathValue,
            ) {
                parent::__construct($context, $configHelper);
                $this->entity = $entityValue;
                $this->redirectPath = $redirectPathValue;
            }
        };
    }

    public function testGetButtonDataReturnsBasicStructureForNonProductEntity(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);
        $button = $this->makeButton('categories', 'algolia/indexingmanager/categories');

        $data = $button->getButtonData();

        $this->assertArrayHasKey('label', $data);
        $this->assertArrayHasKey('on_click', $data);
        $this->assertStringContainsString('Categories', (string) $data['label']);
        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }

    public function testGetButtonDataAddsWarningWhenQueueInactiveAndEntityIsProducts(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);
        $button = $this->makeButton('products', 'algolia/indexingmanager/products');

        $data = $button->getButtonData();

        $this->assertStringContainsString('Warning', $data['on_click']);
        $this->assertStringContainsString('Indexing Queue is not activated', $data['on_click']);
    }

    public function testGetButtonDataDoesNotAddWarningWhenQueueActiveAndEntityIsProducts(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);
        $button = $this->makeButton('products', 'algolia/indexingmanager/products');

        $data = $button->getButtonData();

        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }
}
