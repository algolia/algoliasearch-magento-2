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

    /**
     * @dataProvider valuesProvider
     */
    public function testInput($value, $isValid, $canReplay = true): void
    {
        $this->queueCronModel->setValue($value);

        try {
            $result = $this->queueCronModel->beforeSave();
            $this->assertIsObject($result);
        } catch (InvalidCronException $exception) {
            $this->assertEquals(
                false,
                $isValid,
                "Cron expression \"$value\" is not valid but it should be."
            );

            $msg = $canReplay
                ? "Cron expression \"$value\" is not valid."
                : "Cron expression is invalid.";
            $this->assertEquals(
                $msg,
                $exception->getMessage()
            );
        }
    }

    public static function valuesProvider(): array
    {
        return [
            [
                'value' => '',
                'isValid' => false
            ],
            [
                'value' => 'foo',
                'isValid' => false
            ],
            [
                'value' => '*/5 * * * *',
                'isValid' => true
            ],
            [
                'value' => '*/10 * * * *',
                'isValid' => true
            ],
            [
                'value' => '0 0 1 1 *',
                'isValid' => true
            ],
            [
                'value' => '0 0 * * 5',
                'isValid' => true
            ],
            [
                'value' => '*/10 * * *', // One less property
                'isValid' => false
            ],
            [
                'value' => '*/10 * * * * *', // One more property
                'isValid' => false
            ],
            [
                'value' => '@daily', // Working alias
                'isValid' => true
            ],
            [
                'value' => '@foo', // Not working alias
                'isValid' => false
            ],
            [
                'value' => '"><script>alert(\'XSS\')</script>',
                'isValid' => false,
                'canReplay' => false
            ]
        ];
    }

}
