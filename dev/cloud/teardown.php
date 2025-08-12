<?php

use Magento\Framework\App\Bootstrap;

require '/app/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$algoliaConnector = $objectManager->get(\Algolia\AlgoliaSearch\Service\AlgoliaConnector::class);
$indexOptionsBuilder = $objectManager->get(\Algolia\AlgoliaSearch\Service\IndexOptionsBuilder::class);
$indexNamePrefix = getenv('MAGENTO_CLOUD_ENVIRONMENT');

/**
 * @param $algoliaConnector Algolia\AlgoliaSearch\Service\AlgoliaConnector
 * @param $indexOptionsBuilder Algolia\AlgoliaSearch\Service\IndexOptionsBuilder
 * @param array $indices
 * @param $indexNamePrefix
 */
function deleteIndexes($algoliaConnector, $indexOptionsBuilder, array $indices, $indexNamePrefix)
{
    foreach ($indices['items'] as $index) {
        $name = $index['name'];

        if (mb_strpos((string) $name, (string) $indexNamePrefix) === 0) {
            try {
                $indexOptions = $indexOptionsBuilder->buildWithEnforcedIndex($name);
                $algoliaConnector->deleteIndex($name);
                echo 'Index "' . $name . '" has been deleted.';
                echo "\n";
            } catch (Exception) {
                // Might be a replica
            }
        }
    }
}

if ($algoliaConnector) {
    $indices = $algoliaConnector->listIndexes();
    if (count($indices) > 0) {
        deleteIndexes($algoliaConnector, $indexOptionsBuilder, $indices, $indexNamePrefix);
    }

    $replicas = $algoliaConnector->listIndexes();
    if (count($replicas) > 0) {
        deleteIndexes($algoliaConnector, $indexOptionsBuilder, $replicas, $indexNamePrefix);
    }
}
