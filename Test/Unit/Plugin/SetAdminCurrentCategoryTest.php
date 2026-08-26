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

class SetAdminCurrentCategoryTest extends TestCase
{
    protected function createObjectToTest(
        ?CurrentCategory $currentCategory = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
    ): SetAdminCurrentCategory {
        return new SetAdminCurrentCategory(
            $currentCategory ?? $this->createStub(CurrentCategory::class),
            $categoryRepository ?? $this->createStub(CategoryRepositoryInterface::class),
        );
    }

    private function createSubjectStub(RequestInterface $request): EditController&\PHPUnit\Framework\MockObject\MockObject
    {
        $subject = $this->getMockBuilder(EditController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();
        // getRequest() is unconditionally called exactly once by afterExecute().
        $subject->expects($this->once())->method('getRequest')->willReturn($request);

        return $subject;
    }

    public function testAfterExecuteSetsCurrentCategoryAndReturnsResult(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('id')->willReturn(42);

        $category = $this->createStub(CategoryInterface::class);

        $categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $categoryRepository->method('get')->with(42)->willReturn($category);

        $currentCategory = $this->createMock(CurrentCategory::class);
        $currentCategory->expects($this->once())->method('set')->with($category);

        $plugin = $this->createObjectToTest($currentCategory, $categoryRepository);

        $page = $this->createStub(Page::class);
        $result = $plugin->afterExecute($this->createSubjectStub($request), $page);

        $this->assertSame($page, $result);
    }

    public function testAfterExecuteReturnsNullWhenCategoryNotFound(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('id')->willReturn(999);

        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepository->method('get')->willThrowException(new NoSuchEntityException());

        $currentCategory = $this->createMock(CurrentCategory::class);
        $currentCategory->expects($this->never())->method('set');

        $plugin = $this->createObjectToTest($currentCategory, $categoryRepository);

        $result = $plugin->afterExecute($this->createSubjectStub($request), $this->createStub(Page::class));

        $this->assertNull($result);
    }
}
