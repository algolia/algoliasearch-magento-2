<?php

use Magento\Framework\App\Bootstrap;

require '/app/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$algoliaHelper = $objectManager->get('\Algolia\AlgoliaSearch\Helper\AlgoliaHelper');
$indexNamePrefix = getenv('MAGENTO_CLOUD_ENVIRONMENT');

/**
 * @param $algoliaHelper Algolia\AlgoliaSearch\Helper\AlgoliaHelper
 * @param array $indices
 */
function deleteIndexes($algoliaHelper, array $indices, $indexNamePrefix)
{
    foreach ($indices['items'] as $index) {
        $name = $index['name'];

        if (mb_strpos($name, $indexNamePrefix) === 0) {
            try {
                $algoliaHelper->deleteIndex($name);
                echo 'Index "' . $name . '" has been deleted.';
                echo "\n";
            } catch (Exception $e) {
                // Might be a replica
            }
        }
    }
}

if ($algoliaHelper) {
    $indices = $algoliaHelper->listIndexes();
    if (count($indices) > 0) {
        deleteIndexes($algoliaHelper, $indices, $indexNamePrefix);
    }

    $replicas = $algoliaHelper->listIndexes();
    if (count($replicas) > 0) {
        deleteIndexes($algoliaHelper, $replicas, $indexNamePrefix);
    }
}
