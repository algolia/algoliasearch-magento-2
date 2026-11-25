<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaCredentialsManagerTest extends TestCase
{
    protected const string CALLER = 'Algolia\AlgoliaSearch\Some\Queue\Caller';

    protected const string CONFIG_HINT = ' => You need to configure your credentials'
        . ' in Stores > Configuration > Algolia Search.';

    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?ManagerInterface $messageManager = null,
        ?ConsoleOutput $output = null,
        ?StoreManagerInterface $storeManager = null,
        ?StoreNameFetcher $storeNameFetcher = null
    ): AlgoliaCredentialsManager {
        $storeManager ??= $this->createStoreManager('Second Store');

        return new AlgoliaCredentialsManager(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $messageManager ?? $this->createStub(ManagerInterface::class),
            $output ?? $this->createStub(ConsoleOutput::class),
            $storeManager,
            // A real fetcher - not stubbed so the manager can feed it
            $storeNameFetcher ?? new StoreNameFetcher($storeManager)
        );
    }

    protected function createStoreManager(string $storeName): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getName')->willReturn($storeName);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
    }

    public function testGetErrorMessageNamesTheClassAndTheStoreLabel(): void
    {
        $this->assertSame(
            self::CALLER . ': Algolia credentials missing for Store 2 (Second Store)' . self::CONFIG_HINT,
            $this->createObjectToTest()->getErrorMessage(self::CALLER, 2)
        );
    }

    public function testGetErrorMessageOmitsTheStoreWhenUnscoped(): void
    {
        $this->assertSame(
            self::CALLER . ': Algolia credentials missing' . self::CONFIG_HINT,
            $this->createObjectToTest()->getErrorMessage(self::CALLER)
        );
    }

    public function testGetErrorMessageDegradesToThePlainLabelWhenTheStoreIsUnknown(): void
    {
        $this->assertSame(
            self::CALLER . ': Algolia credentials missing for Store 42' . self::CONFIG_HINT,
            $this->createObjectToTest(storeManager: $this->createUnknownStoreManager())
                ->getErrorMessage(self::CALLER, 42)
        );
    }

    public function testDisplayErrorMessageWritesTheMessageToTheConsole(): void
    {
        // displayErrorMessage() branches on php_sapi_name(), which is always cli under PHPUnit
        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with(self::CALLER . ': Algolia credentials missing' . self::CONFIG_HINT);

        $this->createObjectToTest(output: $output)->displayErrorMessage(self::CALLER);
    }

    public function testDisplayErrorMessageResolvesTheStoreLabelForItsOwnMessage(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with(
                self::CALLER
                    . ': Algolia credentials missing for Store 2 (Second Store)'
                    . self::CONFIG_HINT
            );

        $this->createObjectToTest(output: $output)->displayErrorMessage(self::CALLER, 2);
    }

    public function testDisplayErrorMessageWritesThePlainLabelWhenTheStoreIsUnknown(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with(self::CALLER . ': Algolia credentials missing for Store 42' . self::CONFIG_HINT);

        $this->createObjectToTest(
            output: $output,
            storeManager: $this->createUnknownStoreManager()
        )->displayErrorMessage(self::CALLER, 42);
    }

    protected function createUnknownStoreManager(): StoreManagerInterface
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        return $storeManager;
    }
}
