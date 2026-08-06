<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\QueueArchive;

use Algolia\AlgoliaSearch\Api\Data\QueueArchiveInterface;
use Algolia\AlgoliaSearch\Api\QueueArchiveRepositoryInterface;
use Algolia\AlgoliaSearch\Block\Adminhtml\QueueArchive\View;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ViewTest extends TestCase
{
    protected null|(View&MockObject) $block = null;
    protected null|(QueueArchiveRepositoryInterface&MockObject) $queueArchiveRepository = null;
    protected null|(RequestInterface&MockObject) $request = null;

    protected function setUp(): void
    {
        $this->queueArchiveRepository = $this->createMock(QueueArchiveRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->block = $this->getMockBuilder(View::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();

        $this->block->method('getRequest')->willReturn($this->request);
        $this->setPrivateProperty($this->block, 'queueArchiveRepository', $this->queueArchiveRepository);
    }

    public function testGetCurrentJobLoadsArchiveUsingRequestId(): void
    {
        $archive = $this->createMock(QueueArchiveInterface::class);
        $this->request->method('getParam')->with('id')->willReturn(42);
        $this->queueArchiveRepository->method('getById')->with(42)->willReturn($archive);

        $this->assertSame($archive, $this->block->getCurrentJob());
    }

    public function testGetCurrentJobMemoizesResult(): void
    {
        $archive = $this->createMock(QueueArchiveInterface::class);
        $this->request->method('getParam')->with('id')->willReturn(42);
        $this->queueArchiveRepository->expects($this->once())->method('getById')->with(42)->willReturn($archive);

        $first = $this->block->getCurrentJob();
        $second = $this->block->getCurrentJob();

        $this->assertSame($first, $second);
    }

    public function testGetCurrentJobPropagatesNoSuchEntityException(): void
    {
        $this->request->method('getParam')->with('id')->willReturn(42);
        $this->queueArchiveRepository->method('getById')->with(42)
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(NoSuchEntityException::class);

        $this->block->getCurrentJob();
    }
}
