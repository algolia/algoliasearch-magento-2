<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Cart;

use Algolia\AlgoliaSearch\Block\Cart\Recommend;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote;

class RecommendTest extends TestCase
{
    protected function createObjectToTest(
        ?Session $checkoutSession = null,
        ?ConfigHelper $configHelper = null,
    ): Recommend {
        $context = $this->createStub(Context::class);

        return new Recommend(
            $context,
            $checkoutSession ?? $this->createStub(Session::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
        );
    }

    public function testGetAllCartItemsReturnsEmptyArrayWhenCartIsEmpty(): void
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([]);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession);

        $this->assertSame([], $block->getAllCartItems());
    }

    public function testGetAllCartItemsReturnsProductIdsFromCart(): void
    {
        // Quote\Item::getProductId() is a magic getter (via DataObject::__call), so a plain
        // DataObject stands in for it here instead of mocking an undeclared method.
        $item1 = new DataObject(['product_id' => 10]);
        $item2 = new DataObject(['product_id' => 20]);

        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession);

        $this->assertSame([10, 20], $block->getAllCartItems());
    }

    public function testGetAllCartItemsDeduplicatesProductIds(): void
    {
        $item1 = new DataObject(['product_id' => 10]);
        $item2 = new DataObject(['product_id' => 10]);

        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession);

        $result = $block->getAllCartItems();
        $this->assertCount(1, $result);
        $this->assertContains(10, $result);
    }
}
