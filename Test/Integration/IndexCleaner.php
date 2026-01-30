<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;

/** 
 * Provides a way to clean up indices after individual tests or an entire test suite
 * Can be invoked from either tearDown or tearDownAfterClass
 */
final class IndexCleaner
{
    private static ?AlgoliaConnector $connector = null;
    private static ?IndexOptionsBuilder $optionsBuilder = null;

    public static function init(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        self::$connector ??= $objectManager->get(AlgoliaConnector::class);
        self::$optionsBuilder ??= $objectManager->get(IndexOptionsBuilder::class);
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public static function clean(string $indexPrefix): void
    {
        self::deleteIndices($indexPrefix);
        self::$connector->waitLastTask();
        self::deleteIndices($indexPrefix); // Remaining replicas
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    private static function deleteIndices(string $indexPrefix): void
    {
        $indices = self::$connector->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos((string) $name, $indexPrefix) === 0) {
                try {
                    $indexOptions = self::$optionsBuilder->buildWithEnforcedIndex($name);
                    self::$connector->deleteIndex($indexOptions);
                } catch (AlgoliaException) {
                    // Might be a replica
                }
            }
        }
    }
}
