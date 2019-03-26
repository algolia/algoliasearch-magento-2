<?php

namespace Algolia\AlgoliaSearch\Setup;

use Algolia\AlgoliaSearch\Api\Data\LandingPageInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();

        $connection->dropTable($setup->getTable('algoliasearch_queue'));
        $connection->dropTable($setup->getTable('algoliasearch_queue_log'));
        $connection->dropTable($setup->getTable('algoliasearch_queue_archive'));
        $connection->dropTable($setup->getTable(LandingPageInterface::TABLE_NAME));

        $setup->endSetup();
    }
}
