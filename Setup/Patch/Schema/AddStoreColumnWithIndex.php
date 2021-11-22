<?php
/*
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Setup\Patch\Schema;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class AddStoreColumnWithIndex implements SchemaPatchInterface
{
    /** @var SchemaSetupInterface $schemaSetup */
    private $schemaSetup;

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $connection = $this->schemaSetup->getConnection();
        $connection->startSetup();

        $connection->addColumn($this->schemaSetup->getTable('algoliasearch_queue'), 'store_id', [
            'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
            'unsigned' => true,
            'nullable' => true,
            'comment'  => 'Store ID'
        ]);

        $connection->addForeignKey(
            $this->schemaSetup->getFkName(
                $this->schemaSetup->getTable('algoliasearch_queue'),
                'store_id',
                $this->schemaSetup->getTable('store'),
                'store_id'
            ),
            $this->schemaSetup->getTable('algoliasearch_queue'),
            'store_id',
            $this->schemaSetup->getTable('store'),
            'store_id'
        );

        $connection->addIndex(
            $this->schemaSetup->getTable('algoliasearch_queue'),
            $this->schemaSetup->getIdxName(
                $this->schemaSetup->getTable('algoliasearch_queue'),
                ['locked_at'],
                AdapterInterface::INDEX_TYPE_INDEX
            ),
            ['locked_at'],
            AdapterInterface::INDEX_TYPE_INDEX
        );
        $connection->addIndex(
            $this->schemaSetup->getTable('algoliasearch_queue'),
            $this->schemaSetup->getIdxName(
                $this->schemaSetup->getTable('algoliasearch_queue'),
                ['pid'],
                AdapterInterface::INDEX_TYPE_INDEX
            ),
            ['pid'],
            AdapterInterface::INDEX_TYPE_INDEX
        );

        $connection->endSetup();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
