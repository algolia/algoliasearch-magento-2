<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use PHPUnit\Framework\TestCase;

abstract class AbstractHandlerTest extends TestCase
{
    protected ?Base $handler = null;

    /**
     * Monolog v3 uses LogRecord
     * v2 uses arrays only
     */
    protected function makeLogRecord(int $level, string $message)
    {
        if (class_exists(\Monolog\LogRecord::class)) {
            // Monolog v3
            $levelEnum = $this->convertLevelToEnum($level);
            return new \Monolog\LogRecord(
                new \DateTimeImmutable(),
                'test',
                $levelEnum,
                $message
            );
        }

        // Monolog v2
        return [
            'message' => $message,
            'level' => $level
        ];
    }

    protected function convertLevelToEnum(int $level): \Monolog\Level
    {
        if (class_exists(\Monolog\Level::class)) {
            return \Monolog\Level::from($level);
        }

        throw new \RuntimeException('Monolog v3 Level enum not available');
    }

}
