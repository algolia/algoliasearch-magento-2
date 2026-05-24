<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\SetAdminCurrentCategory;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Controller\Adminhtml\Category\Edit as EditController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use PHPUnit\Framework\MockObject\MockObject;

class SetAdminCurrentCategoryTest extends TestCase
{
    protected null|(CurrentCategory&MockObject) $currentCategory = null;
    protected null|(CategoryRepositoryInterface&MockObject) $categoryRepository = null;
    protected null|(EditController&MockObject) $subject = null;
    protected null|(RequestInterface&MockObject) $request = null;
    protected ?SetAdminCurrentCategory $plugin = null;

    protected function setUp(): void
    {
        $this->currentCategory = $this->createMock(CurrentCategory::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->subject = $this->getMockBuilder(EditController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();
        $this->subject->method('getRequest')->willReturn($this->request);

        $this->plugin = new SetAdminCurrentCategory($this->currentCategory, $this->categoryRepository);
    }

    public function testAfterExecuteSetsCurrentCategoryAndReturnsResult(): void
    {
        $this->request->method('getParam')->with('id')->willReturn(42);

        $category = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')->with(42)->willReturn($category);

        $this->currentCategory->expects($this->once())->method('set')->with($category);

        $page = $this->createMock(Page::class);
        $result = $this->plugin->afterExecute($this->subject, $page);

        $this->assertSame($page, $result);
    }

    public function testAfterExecuteReturnsNullWhenCategoryNotFound(): void
    {
        $this->request->method('getParam')->with('id')->willReturn(999);

        $this->categoryRepository->method('get')
            ->willThrowException(new NoSuchEntityException());

        $this->currentCategory->expects($this->never())->method('set');

        $result = $this->plugin->afterExecute($this->subject, $this->createMock(Page::class));

        $this->assertNull($result);
    }
}
