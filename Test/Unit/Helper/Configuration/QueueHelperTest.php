<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class QueueHelperTest extends TestCase
{
    protected function createObjectToTest(?ScopeConfigInterface $configInterface = null): QueueHelper
    {
        return new QueueHelper(
            $configInterface ?? $this->createStub(ScopeConfigInterface::class),
        );
    }

    public function testIsQueueActiveReadsConfigFlagForGivenScope(): void
    {
        $configInterface = $this->createMock(ScopeConfigInterface::class);
        $configInterface->method('isSetFlag')->with(
            QueueHelper::IS_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            '1'
        )->willReturn(true);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertTrue($queueHelper->isQueueActive('1'));
    }

    public function testIsEnhancedQueueArchiveEnabledReadsConfigFlagForGivenScope(): void
    {
        $configInterface = $this->createMock(ScopeConfigInterface::class);
        $configInterface->method('isSetFlag')->with(
            QueueHelper::ENHANCED_QUEUE_ARCHIVE,
            ScopeInterface::SCOPE_STORE,
            '2'
        )->willReturn(true);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertTrue($queueHelper->isEnhancedQueueArchiveEnabled('2'));
    }

    public function testIsEnhancedQueueArchiveEnabledDefaultsToFalseWhenFlagIsNotSet(): void
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('isSetFlag')->willReturn(false);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertFalse($queueHelper->isEnhancedQueueArchiveEnabled());
    }

    #[DataProvider('numberOfJobToRunProvider')]
    public function testGetNumberOfJobToRunClampsConfigValueToOne($configValue, int $expected): void
    {
        $configInterface = $this->createMock(ScopeConfigInterface::class);
        $configInterface->expects($this->once())->method('getValue')->with(
            QueueHelper::NUMBER_OF_JOB_TO_RUN,
            ScopeInterface::SCOPE_STORE,
            null
        )->willReturn($configValue);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertSame($expected, $queueHelper->getNumberOfJobToRun());
    }

    public static function numberOfJobToRunProvider(): array
    {
        return [
            'positive int value' => [7, 7],
            'numeric string value' => ['10', 10],
            'zero value is clamped to one' => [0, 1],
            'negative value is clamped to one' => [-5, 1],
            'null value is clamped to one' => [null, 1],
        ];
    }

    public function testUseTmpIndexRequiresBothQueueActiveAndTmpIndexFlags(): void
    {
        $configInterface = $this->createMock(ScopeConfigInterface::class);
        $configInterface->expects($this->exactly(2))->method('isSetFlag')->willReturnMap([
            [QueueHelper::IS_ACTIVE, ScopeInterface::SCOPE_STORE, '1', true],
            [QueueHelper::USE_TMP_INDEX, ScopeInterface::SCOPE_STORE, '1', true],
        ]);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertTrue($queueHelper->useTmpIndex('1'));
    }

    public function testUseTmpIndexReturnsFalseWhenQueueIsInactiveEvenIfTmpIndexFlagIsSet(): void
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('isSetFlag')->willReturnMap([
            [QueueHelper::IS_ACTIVE, ScopeInterface::SCOPE_STORE, '1', false],
            [QueueHelper::USE_TMP_INDEX, ScopeInterface::SCOPE_STORE, '1', true],
        ]);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertFalse($queueHelper->useTmpIndex('1'));
    }

    public function testUseTmpIndexReturnsFalseWhenTmpIndexFlagIsNotSet(): void
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('isSetFlag')->willReturnMap([
            [QueueHelper::IS_ACTIVE, ScopeInterface::SCOPE_STORE, null, true],
            [QueueHelper::USE_TMP_INDEX, ScopeInterface::SCOPE_STORE, null, false],
        ]);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertFalse($queueHelper->useTmpIndex());
    }

    #[DataProvider('archiveLogClearLimitProvider')]
    public function testGetArchiveLogClearLimitCastsTheConfigValueToInt($configValue, int $expected): void
    {
        $configInterface = $this->createMock(ScopeConfigInterface::class);
        $configInterface->expects($this->once())->method('getValue')->with(
            QueueHelper::ARCHIVE_LOG_CLEAR_LIMIT,
            ScopeInterface::SCOPE_STORE,
            null
        )->willReturn($configValue);

        $queueHelper = $this->createObjectToTest(configInterface: $configInterface);

        $this->assertSame($expected, $queueHelper->getArchiveLogClearLimit());
    }

    public static function archiveLogClearLimitProvider(): array
    {
        return [
            'int value' => [30, 30],
            'numeric string value' => ['15', 15],
            'null value' => [null, 0],
        ];
    }
}
