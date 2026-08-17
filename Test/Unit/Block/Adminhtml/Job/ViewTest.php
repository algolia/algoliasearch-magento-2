<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Job;

use Algolia\AlgoliaSearch\Block\Adminhtml\Job\View;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResource;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template\Context;

class ViewTest extends TestCase
{
    protected function createObjectToTest(
        ?JobFactory $jobFactory = null,
        ?JobResource $jobResource = null,
        ?RequestInterface $request = null,
    ): View {
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request ?? $this->createStub(RequestInterface::class));

        return new View(
            $context,
            $jobFactory ?? $this->createStub(JobFactory::class),
            $jobResource ?? $this->createStub(JobResource::class),
        );
    }

    public function testGetCurrentJobLoadsJobUsingRequestId(): void
    {
        $job = $this->createStub(Job::class);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(42);

        $jobFactory = $this->createStub(JobFactory::class);
        $jobFactory->method('create')->willReturn($job);

        $jobResource = $this->createMock(JobResource::class);
        $jobResource->expects($this->once())->method('load')->with($job, 42);

        $block = $this->createObjectToTest(jobFactory: $jobFactory, jobResource: $jobResource, request: $request);

        $this->assertSame($job, $block->getCurrentJob());
    }

    public function testGetCurrentJobCastsStringIdToInt(): void
    {
        $job = $this->createStub(Job::class);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('42');

        $jobFactory = $this->createStub(JobFactory::class);
        $jobFactory->method('create')->willReturn($job);

        $jobResource = $this->createMock(JobResource::class);
        $jobResource->expects($this->once())->method('load')->with($job, 42);

        $block = $this->createObjectToTest(jobFactory: $jobFactory, jobResource: $jobResource, request: $request);

        $block->getCurrentJob();
    }

    public function testGetCurrentJobMemoizesResult(): void
    {
        $job = $this->createStub(Job::class);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(42);

        $jobFactory = $this->createMock(JobFactory::class);
        $jobFactory->expects($this->once())->method('create')->willReturn($job);

        $jobResource = $this->createMock(JobResource::class);
        $jobResource->expects($this->once())->method('load')->with($job, 42);

        $block = $this->createObjectToTest(jobFactory: $jobFactory, jobResource: $jobResource, request: $request);

        $first = $block->getCurrentJob();
        $second = $block->getCurrentJob();

        $this->assertSame($first, $second);
    }
}
