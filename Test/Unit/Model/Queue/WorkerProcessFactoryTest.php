<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessFactory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessFactory;

class WorkerProcessFactoryTest extends TestCase
{
    /**
     * The injected ProcessFactory and ObjectManager stubs construct real Process and
     * WorkerProcess objects from the options they receive, so the factory's built output
     * (cwd, timeout, command) is asserted against genuine behavior rather than stubs.
     */
    protected function createObjectToTest(
        ?ObjectManagerInterface $objectManager = null,
        ?ProcessFactory $processFactory = null,
        ?PhpExecutableFinder $phpFinder = null,
        ?DirectoryList $directoryList = null,
    ): WorkerProcessFactory {
        $objectManager ??= $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturnCallback(
            function (string $className, array $arguments = []) {
                return new $className($arguments['storeId'], $arguments['process']);
            }
        );

        $processFactory ??= $this->createStub(ProcessFactory::class);
        $processFactory->method('create')->willReturnCallback(
            function (array $options) {
                return new Process($options['command'], $options['cwd']);
            }
        );

        $phpFinder ??= $this->createStub(PhpExecutableFinder::class);
        $phpFinder->method('find')->willReturn('/fake/php');

        $directoryList ??= $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento/root');

        return new WorkerProcessFactory($objectManager, $processFactory, $phpFinder, $directoryList);
    }

    public function testCreateReturnsWorkerCarryingTheStoreId(): void
    {
        $worker = $this->createObjectToTest()->create(2);

        $this->assertInstanceOf(WorkerProcess::class, $worker);
        $this->assertSame(2, $worker->getStoreId());
    }

    public function testCreateBuildsAWorkerProcessForTheGivenStore(): void
    {
        $worker = $this->createObjectToTest()->create(2);
        $process = $this->getPrivateProperty($worker, 'process');

        $this->assertInstanceOf(Process::class, $process);
        $this->assertSame('/magento/root', $process->getWorkingDirectory());
        $this->assertNull($process->getTimeout());
        $commandLine = $process->getCommandLine();
        $this->assertStringContainsString('/fake/php', $commandLine);
        $this->assertStringContainsString('bin/magento', $commandLine);
        $this->assertStringContainsString('algolia:queue:process', $commandLine);
        $this->assertStringContainsString("'2'", $commandLine);
    }

    public function testCreatePassesBuildCommandResultToTheProcessFactory(): void
    {
        $createdOptions = [];
        $processFactory = $this->createStub(ProcessFactory::class);
        $processFactory->method('create')->willReturnCallback(
            function (array $options) use (&$createdOptions) {
                $createdOptions[] = $options;

                return new Process($options['command'], $options['cwd']);
            }
        );

        $worker = $this->createObjectToTest(processFactory: $processFactory)->create(2);

        $this->assertSame(
            ['/fake/php', 'bin/magento', 'algolia:queue:process', '2'],
            $createdOptions[0]['command']
        );
        $this->assertSame('/magento/root', $createdOptions[0]['cwd']);
        $this->assertSame(2, $worker->getStoreId());
    }

    public function testBuildCommandSeamProducesPhpBinaryMagentoAndStoreId(): void
    {
        $command = $this->invokeMethod($this->createObjectToTest(), 'buildCommand', [2]);

        $this->assertSame(['/fake/php', 'bin/magento', 'algolia:queue:process', '2'], $command);
    }

    public function testFindPhpBinaryReturnsTheInjectedFinderResult(): void
    {
        $this->assertSame('/fake/php', $this->invokeMethod($this->createObjectToTest(), 'findPhpBinary'));
    }

    public function testFindPhpBinaryFallsBackToPhpBinaryWhenFinderFails(): void
    {
        $phpFinder = $this->createStub(PhpExecutableFinder::class);
        $phpFinder->method('find')->willReturn(false);

        $this->assertSame(
            PHP_BINARY,
            $this->invokeMethod($this->createObjectToTest(phpFinder: $phpFinder), 'findPhpBinary')
        );
    }
}
