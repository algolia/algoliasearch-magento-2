<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Insights;

use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Magento\Directory\Model\Currency;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class EventProcessorTest extends TestCase
{
    private ?EventProcessor $eventProcessor = null;
    private ?InsightsClient $insightsClient = null;
    private ?StoreManagerInterface $storeManager = null;
    private ?Store $store = null;
    private ?Currency $currency = null;

    public function setUp(): void
    {
        $this->insightsClient = $this->createMock(InsightsClient::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->createMock(Store::class);
        $this->currency = $this->createMock(Currency::class);

        $this->eventProcessor = new EventProcessor();
    }

    // Test dependency validation and setup methods

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenClientMissing(): void
    {
        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenUserTokenMissing(): void
    {
        $this->eventProcessor->setInsightsClient($this->insightsClient);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenStoreManagerMissing(): void
    {
        $this->eventProcessor
            ->setInsightsClient($this->insightsClient)
            ->setAnonymousUserToken('user-token');

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }
}
