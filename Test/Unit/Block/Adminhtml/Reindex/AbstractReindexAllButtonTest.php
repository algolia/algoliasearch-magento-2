<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Reindex;

use Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\AbstractReindexAllButton;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class AbstractReindexAllButtonTest extends TestCase
{
    protected function createObjectToTest(
        string $entity,
        string $redirectPath,
        ?ConfigHelper $configHelper = null,
    ): AbstractReindexAllButton {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->with('algolia_algoliasearch/indexingmanager/reindex')->willReturn('http://example.com/reindex');

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        return new class(
            $context,
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $entity,
            $redirectPath,
        ) extends AbstractReindexAllButton {
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
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $button = $this->createObjectToTest('categories', 'algolia/indexingmanager/categories', $configHelper);

        $data = $button->getButtonData();

        $this->assertArrayHasKey('label', $data);
        $this->assertArrayHasKey('on_click', $data);
        $this->assertStringContainsString('Categories', (string) $data['label']);
        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }

    public function testGetButtonDataAddsWarningWhenQueueInactiveAndEntityIsProducts(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $button = $this->createObjectToTest('products', 'algolia/indexingmanager/products', $configHelper);

        $data = $button->getButtonData();

        $this->assertStringContainsString('Warning', $data['on_click']);
        $this->assertStringContainsString('Indexing Queue is not activated', $data['on_click']);
    }

    public function testGetButtonDataDoesNotAddWarningWhenQueueActiveAndEntityIsProducts(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $button = $this->createObjectToTest('products', 'algolia/indexingmanager/products', $configHelper);

        $data = $button->getButtonData();

        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }
}
