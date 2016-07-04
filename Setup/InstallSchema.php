<?php

namespace Algolia\AlgoliaSearch\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $installer, ModuleContextInterface $moduleContextInterface)
    {
        $installer->startSetup();

        $connection = $installer->getConnection();
        $table = $connection->newTable($installer->getTable('algoliasearch_queue'));

        $table->addColumn('job_id', $table::TYPE_INTEGER, 20, ['identity' => true, 'nullable' => false, 'primary' => true]);
        $table->addColumn('pid', $table::TYPE_INTEGER, 20, ['nullable' => true, 'default' => null]);
        $table->addColumn('class', $table::TYPE_TEXT, 50, ['nullable' => false]);
        $table->addColumn('method', $table::TYPE_TEXT, 50, ['nullable' => false]);
        $table->addColumn('data', $table::TYPE_TEXT, 5000, ['nullable' => false]);
        $table->addColumn('max_retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'default' => 3]);
        $table->addColumn('retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'defualt' => 0]);
        $table->addColumn('error_log', $table::TYPE_TEXT, null, ['nullable' => false]);
        $table->addColumn('data_size', $table::TYPE_INTEGER, 11, ['nullable' => true, 'default' => null]);

        $connection->createTable($table);

        $installer->endSetup();
    }
}
