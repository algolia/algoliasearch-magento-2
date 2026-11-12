<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Output\BufferedOutput;

class AbstractStoreCommandTest extends TestCase
{
    protected function createObjectToTest(
        ?State $state = null,
        ?StoreNameFetcher $storeNameFetcher = null,
    ): AbstractStoreCommand {
        return new class(
            $state ?? $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
        ) extends AbstractStoreCommand {
            protected function getCommandName(): string
            {
                return 'stub';
            }

            protected function getCommandDescription(): string
            {
                return 'Stub command for AbstractStoreCommand tests.';
            }

            protected function getStoreArgumentDescription(): string
            {
                return 'Store IDs.';
            }

            protected function getAdditionalDefinition(): array
            {
                return [];
            }
        };
    }

    public function testStoreIdArgumentDefinition(): void
    {
        $args = $this->createObjectToTest()->getDefinition()->getArguments();

        $this->assertArrayHasKey('store_id', $args);
        $this->assertTrue($args['store_id']->isArray());
        $this->assertFalse($args['store_id']->isRequired());
    }

    /**
     * @throws \ReflectionException
     */
    public function testSetAreaCodeSwallowsLocalizedException(): void
    {
        $state = $this->createMock(State::class);
        $state->expects($this->once())
            ->method('setAreaCode')
            ->with(Area::AREA_CRONTAB)
            ->willThrowException(new LocalizedException(__('already set')));

        $cmd = $this->createObjectToTest(state: $state);
        $output = new BufferedOutput();
        $this->setPrivateProperty($cmd, 'output', $output);

        $this->invokeMethod($cmd, 'setAreaCode');

        $written = $output->fetch();
        $this->assertStringContainsString('Unable to set area code', $written);
        $this->assertStringContainsString('already set', $written);
    }
}
