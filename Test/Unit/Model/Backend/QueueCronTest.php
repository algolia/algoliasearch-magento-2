<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Backend;

use Algolia\AlgoliaSearch\Exception\InvalidCronException;
use Algolia\AlgoliaSearch\Model\Backend\QueueCron;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class QueueCronTest extends TestCase
{
    protected ?QueueCron $queueCronModel;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $eventDispatcher = $this->createMock(ManagerInterface::class);
        $context->method('getEventDispatcher')->willReturn($eventDispatcher);

        $registry = $this->createMock(Registry::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $cacheTypeList = $this->createMock(TypeListInterface::class);

        $this->queueCronModel = new QueueCron($context, $registry, $config, $cacheTypeList);
    }

    public function testInput(): void
    {
        $valuesToTest = [
            ''               => false,
            'foo'            => false,
            '*/5 * * * *'    => true,
            '*/10 * * * *'   => true,
            '0 0 1 1 *'      => true,
            '0 0 * * 5'      => true,
            '*/10 * * *'     => false, // One less property
            '*/10 * * * * *' => false, // One more property
            '@daily'         => true,  // Working alias
            '@foo'           => false, // Not working alias
        ];

        foreach ($valuesToTest as $valueToTest => $isValid) {
            $this->queueCronModel->setValue($valueToTest);

            try {
                $result = $this->queueCronModel->beforeSave();
                $this->assertIsObject($result);
            } catch (InvalidCronException $exception) {
                $this->assertEquals(
                    false,
                    $isValid,
                    "Cron expression \"$valueToTest\" is not valid but it should be."
                );

                $this->assertEquals(
                    "Cron expression \"$valueToTest\" is not valid.",
                    $exception->getMessage()
                );
            }
        }
    }

}
