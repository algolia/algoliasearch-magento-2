<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Reindex;

use Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\AbstractReindexAllButton;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;

class AbstractReindexAllButtonTest extends TestCase
{
    protected function createObjectToTest(
        string $entity,
        string $redirectPath,
        ?QueueHelper $queueHelper = null,
    ): AbstractReindexAllButton {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->with('algolia_algoliasearch/indexingmanager/reindex')->willReturn('http://example.com/reindex');

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        return new class(
            $context,
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $entity,
            $redirectPath,
        ) extends AbstractReindexAllButton {
            public function __construct(
                Context $context,
                QueueHelper $queueHelper,
                private string $entityValue,
                private string $redirectPathValue,
            ) {
                parent::__construct($context, $queueHelper);
                $this->entity = $entityValue;
                $this->redirectPath = $redirectPathValue;
            }
        };
    }

    public function testGetButtonDataReturnsBasicStructureForNonProductEntity(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(false);

        $button = $this->createObjectToTest('categories', 'algolia/indexingmanager/categories', $queueHelper);

        $data = $button->getButtonData();

        $this->assertArrayHasKey('label', $data);
        $this->assertArrayHasKey('on_click', $data);
        $this->assertStringContainsString('Categories', (string) $data['label']);
        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }

    public function testGetButtonDataAddsWarningWhenQueueInactiveAndEntityIsProducts(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(false);

        $button = $this->createObjectToTest('products', 'algolia/indexingmanager/products', $queueHelper);

        $data = $button->getButtonData();

        $this->assertStringContainsString('Warning', $data['on_click']);
        $this->assertStringContainsString('Indexing Queue is not activated', $data['on_click']);
    }

    public function testGetButtonDataDoesNotAddWarningWhenQueueActiveAndEntityIsProducts(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(true);

        $button = $this->createObjectToTest('products', 'algolia/indexingmanager/products', $queueHelper);

        $data = $button->getButtonData();

        $this->assertStringNotContainsString('Warning', $data['on_click']);
    }
}
