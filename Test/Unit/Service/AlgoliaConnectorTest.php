<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The temp-index settings merge must strip `semanticSearch` from the
 * online (production) settings before writing them back. An empty
 * `semanticSearch` object round-trips through PHP as an empty array and
 * re-encodes as a JSON array, which the API rejects with a BadRequestException
 * ("cannot unmarshal array into ... semanticSearch").
 *
 * Scope: this 3.18.x patch ships only the semanticSearch coverage. The full
 * AlgoliaConnector unit suite will land with 3.19. 
 *
 * The client is mocked and injected into the protected `clients` cache so that
 * getClient() returns it without opening a real connection or validating
 * credentials.
 */
class AlgoliaConnectorTest extends TestCase
{
    private ?AlgoliaConnector $connector = null;
    private null|(IndexOptionsInterface&MockObject) $indexOptions = null;
    private null|(SearchClient&MockObject) $client = null;

    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function setUp(): void
    {
        $config = $this->createMock(ConfigHelper::class);
        $config->method('getNonCastableAttributes')->willReturn([]);

        $this->connector = new AlgoliaConnector(
            $config,
            $this->createMock(ManagerInterface::class),
            $this->createMock(ConsoleOutput::class),
            $this->createMock(AlgoliaCredentialsManager::class),
            $this->createMock(IndexNameFetcher::class),
            $this->createMock(IndexOptionsBuilder::class)
        );

        // Inject a mocked client so getClient() short-circuits creation.
        $this->client = $this->createMock(SearchClient::class);
        $this->setPrivateProperty(
            $this->connector,
            'clients',
            [self::STORE_ID => $this->client]
        );

        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->indexOptions->method('getStoreId')->willReturn(self::STORE_ID);
        $this->indexOptions->method('getIndexName')->willReturn(self::INDEX_NAME);
    }

    protected function tearDown(): void
    {
        $this->connector = null;
        $this->indexOptions = null;
        $this->client = null;
    }

    /**
     * Online settings containing `semanticSearch` must not appear in the
     * payload passed to the client's setSettings() during the temp-index merge.
     */
    public function testSetSettingsStripsSemanticSearchFromMergedOnlineSettings(): void
    {
        $onlineSettings = [
            'searchableAttributes' => ['name', 'description'],
            'customRanking'        => ['desc(popularity)'],
            'semanticSearch'       => [], // empty object as returned by getSettings; the offending value
        ];
        $localSettings = ['attributesToSnippet' => ['description:10']];

        // Merge source: the live production index.
        $this->client->method('getSettings')
            ->with('magento2_default_products')
            ->willReturn($onlineSettings);

        $this->client->expects($this->once())
            ->method('setSettings')
            ->with(
                self::INDEX_NAME,
                $this->callback(function (array $merged) {
                    $this->assertArrayNotHasKey(
                        'semanticSearch',
                        $merged,
                        'semanticSearch must be stripped before the temp-index settings write'
                    );
                    // Other online settings and the local override still pass through.
                    $this->assertArrayHasKey('searchableAttributes', $merged);
                    $this->assertArrayHasKey('customRanking', $merged);
                    $this->assertArrayHasKey('attributesToSnippet', $merged);

                    return true;
                }),
                false
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->setSettings(
            $this->indexOptions,
            $localSettings,
            false,
            true,
            'magento2_default_products'
        );
    }

    /**
     * Pins the strip list directly so the regression cannot be reintroduced by
     * an edit to getSettingsToRemove() that drops the semanticSearch entry.
     *
     * @throws ReflectionException
     */
    public function testGetSettingsToRemoveIncludesSemanticSearch(): void
    {
        $removals = $this->invokeMethod($this->connector, 'getSettingsToRemove', [[]]);

        $this->assertContains('semanticSearch', $removals);
    }
}
