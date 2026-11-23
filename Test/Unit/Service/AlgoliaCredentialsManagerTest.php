<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaCredentialsManagerTest extends TestCase
{
    protected const CALLER = 'Algolia\AlgoliaSearch\Some\Queue\Caller';

    protected const CONFIG_HINT = '  => You need to configure your credentials'
        . ' in Stores > Configuration > Algolia Search.';

    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?ManagerInterface $messageManager = null,
        ?ConsoleOutput $output = null,
        ?StoreManagerInterface $storeManager = null
    ): AlgoliaCredentialsManager {
        return new AlgoliaCredentialsManager(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $messageManager ?? $this->createStub(ManagerInterface::class),
            $output ?? $this->createStub(ConsoleOutput::class),
            $storeManager ?? $this->createStoreManager('Second Store')
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

    public function testGetErrorMessageNamesTheClassAndTheGivenStoreName(): void
    {
        $this->assertSame(
            self::CALLER . ': Algolia credentials missing for store Second Store' . "\n" . self::CONFIG_HINT,
            $this->createObjectToTest()->getErrorMessage(self::CALLER, 'Second Store')
        );
    }

    public function testGetErrorMessageOmitsTheStoreWhenUnscoped(): void
    {
        $this->assertSame(
            self::CALLER . ': Algolia credentials missing' . "\n" . self::CONFIG_HINT,
            $this->createObjectToTest()->getErrorMessage(self::CALLER)
        );
    }

    /**
     * The builder never resolves store names: callers hand it what they already fetched, so a
     * worker that resolved the name for its other output does not look the store up twice.
     */
    public function testGetErrorMessageDoesNotResolveStores(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $this->createObjectToTest(storeManager: $storeManager)->getErrorMessage(self::CALLER, 'Second Store');
    }

    public function testDisplayErrorMessageWritesTheMessageToTheConsole(): void
    {
        // displayErrorMessage() branches on php_sapi_name(), which is always cli under PHPUnit
        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with(self::CALLER . ': Algolia credentials missing' . "\n" . self::CONFIG_HINT);

        $this->createObjectToTest(output: $output)->displayErrorMessage(self::CALLER);
    }

    public function testDisplayErrorMessageResolvesTheStoreNameForItsOwnMessage(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with(
                self::CALLER . ': Algolia credentials missing for store Second Store' . "\n" . self::CONFIG_HINT
            );

        $this->createObjectToTest(output: $output)->displayErrorMessage(self::CALLER, 2);
    }

    public function testDisplayErrorMessageFallsBackToTheRawErrorWhenTheStoreIsUnknown(): void
    {
        $messageManager = $this->createMock(ManagerInterface::class);
        $messageManager->expects($this->once())
            ->method('addErrorMessage')
            // The manager adds a Phrase, so the assertion stringifies it
            ->with($this->callback(fn($message) => str_contains((string) $message, 'Unable to locate store details')));

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        $this->createObjectToTest(messageManager: $messageManager, storeManager: $storeManager)
            ->displayErrorMessage(self::CALLER, 42);
    }
}
