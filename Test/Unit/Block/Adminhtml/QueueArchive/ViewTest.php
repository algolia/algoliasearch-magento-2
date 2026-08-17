<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\QueueArchive;

use Algolia\AlgoliaSearch\Api\Data\QueueArchiveInterface;
use Algolia\AlgoliaSearch\Api\QueueArchiveRepositoryInterface;
use Algolia\AlgoliaSearch\Block\Adminhtml\QueueArchive\View;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;

class ViewTest extends TestCase
{
    protected function createObjectToTest(
        ?QueueArchiveRepositoryInterface $queueArchiveRepository = null,
        ?RequestInterface $request = null,
    ): View {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request ?? $this->createStub(RequestInterface::class));

        return new View(
            $context,
            $queueArchiveRepository ?? $this->createStub(QueueArchiveRepositoryInterface::class),
        );
    }

    public function testGetCurrentJobLoadsArchiveUsingRequestId(): void
    {
        $archive = $this->createStub(QueueArchiveInterface::class);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(42);

        $queueArchiveRepository = $this->createStub(QueueArchiveRepositoryInterface::class);
        $queueArchiveRepository->method('getById')->willReturn($archive);

        $block = $this->createObjectToTest(queueArchiveRepository: $queueArchiveRepository, request: $request);

        $this->assertSame($archive, $block->getCurrentJob());
    }

    public function testGetCurrentJobMemoizesResult(): void
    {
        $archive = $this->createStub(QueueArchiveInterface::class);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(42);

        $queueArchiveRepository = $this->createMock(QueueArchiveRepositoryInterface::class);
        $queueArchiveRepository->expects($this->once())->method('getById')->with(42)->willReturn($archive);

        $block = $this->createObjectToTest(queueArchiveRepository: $queueArchiveRepository, request: $request);

        $first = $block->getCurrentJob();
        $second = $block->getCurrentJob();

        $this->assertSame($first, $second);
    }

    public function testGetCurrentJobPropagatesNoSuchEntityException(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(42);

        $queueArchiveRepository = $this->createStub(QueueArchiveRepositoryInterface::class);
        $queueArchiveRepository->method('getById')->willThrowException(new NoSuchEntityException());

        $block = $this->createObjectToTest(queueArchiveRepository: $queueArchiveRepository, request: $request);

        $this->expectException(NoSuchEntityException::class);

        $block->getCurrentJob();
    }
}
