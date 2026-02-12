<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Provides a way to clean up indices after individual tests or an entire test suite
 * Can be invoked from either tearDown or tearDownAfterClass
 */
final class IndexCleaner
{
    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public static function clean(string $indexPrefix): void
    {
        $om = Bootstrap::getObjectManager();
        $credentialsManager = $om->get(AlgoliaCredentialsManager::class);
        if (!$credentialsManager->checkCredentials()) {
            return;
        }
        self::deleteIndices($indexPrefix);
        $connector = $om->get(AlgoliaConnector::class);
        $connector->waitLastTask();
        self::deleteIndices($indexPrefix); // Remaining replicas
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    private static function deleteIndices(string $indexPrefix): void
    {
        $om = Bootstrap::getObjectManager();
        $connector = $om->get(AlgoliaConnector::class);
        $optionsBuilder = $om->get(IndexOptionsBuilder::class);
        $indices = $connector->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos((string) $name, $indexPrefix) === 0) {
                try {
                    $indexOptions = $optionsBuilder->buildWithEnforcedIndex($name);
                    $connector->deleteIndex($indexOptions);
                } catch (AlgoliaException) {
                    // Might be a replica
                }
            }
        }
    }
}
