<?php

namespace Algolia\AlgoliaSearch\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (!$context->getVersion() || version_compare($context->getVersion(), '1.0.0') < 0) {
            $connection = $setup->getConnection();
            $table = $connection->newTable($setup->getTable('algoliasearch_queue'));

            $table->addColumn('job_id', $table::TYPE_INTEGER, 20,
                ['identity' => true, 'nullable' => false, 'primary' => true]);
            $table->addColumn('pid', $table::TYPE_INTEGER, 20, ['nullable' => true, 'default' => null]);
            $table->addColumn('class', $table::TYPE_TEXT, 50, ['nullable' => false]);
            $table->addColumn('method', $table::TYPE_TEXT, 50, ['nullable' => false]);
            $table->addColumn('data', $table::TYPE_TEXT, 5000, ['nullable' => false]);
            $table->addColumn('max_retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'default' => 3]);
            $table->addColumn('retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'default' => 0]);
            $table->addColumn('error_log', $table::TYPE_TEXT, null, ['nullable' => false]);
            $table->addColumn('data_size', $table::TYPE_INTEGER, 11, ['nullable' => true, 'default' => null]);

            $connection->createTable($table);
        }

        $setup->endSetup();
    }
}
