<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Job;

use Algolia\AlgoliaSearch\Block\Adminhtml\Job\View;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResource;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ViewTest extends TestCase
{
    protected null|(View&MockObject) $block = null;
    protected null|(JobFactory&MockObject) $jobFactory = null;
    protected null|(JobResource&MockObject) $jobResource = null;
    protected null|(RequestInterface&MockObject) $request = null;

    protected function setUp(): void
    {
        $this->jobFactory = $this->createMock(JobFactory::class);
        $this->jobResource = $this->createMock(JobResource::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->block = $this->getMockBuilder(View::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();

        $this->block->method('getRequest')->willReturn($this->request);
        $this->setPrivateProperty($this->block, 'jobFactory', $this->jobFactory);
        $this->setPrivateProperty($this->block, 'jobResource', $this->jobResource);
    }

    public function testGetCurrentJobLoadsJobUsingRequestId(): void
    {
        $job = $this->createMock(Job::class);
        $this->request->method('getParam')->with('id')->willReturn(42);
        $this->jobFactory->method('create')->willReturn($job);
        $this->jobResource->expects($this->once())->method('load')->with($job, 42);

        $this->assertSame($job, $this->block->getCurrentJob());
    }

    public function testGetCurrentJobCastsStringIdToInt(): void
    {
        $job = $this->createMock(Job::class);
        $this->request->method('getParam')->with('id')->willReturn('42');
        $this->jobFactory->method('create')->willReturn($job);
        $this->jobResource->expects($this->once())->method('load')->with($job, 42);

        $this->block->getCurrentJob();
    }

    public function testGetCurrentJobMemoizesResult(): void
    {
        $job = $this->createMock(Job::class);
        $this->request->method('getParam')->with('id')->willReturn(42);
        $this->jobFactory->expects($this->once())->method('create')->willReturn($job);
        $this->jobResource->expects($this->once())->method('load')->with($job, 42);

        $first = $this->block->getCurrentJob();
        $second = $this->block->getCurrentJob();

        $this->assertSame($first, $second);
    }
}
