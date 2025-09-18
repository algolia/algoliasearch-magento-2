<?php

namespace Algolia\AlgoliaSearch\Setup\Patch;

trait DataMigrationTrait
{
    /**
     * @param array $configurations
     * @return void
     */
    public function migrateConfig(array $configurations): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        foreach ($configurations as $from => $to) {
            $configDataTable = $this->moduleDataSetup->getTable('core_config_data');
            $whereConfigPathFrom = $connection->quoteInto('path = ?', $from);
            $whereConfigPathTo = $connection->quoteInto('path = ?', $to);

            $select = $connection->select()
                ->from($configDataTable)
                ->where($whereConfigPathTo);
            $existingValues = $connection->fetchAll($select);

            if (count($existingValues) === 0) {
                $connection->update($configDataTable, ['path' => $to], $whereConfigPathFrom);
            }
        }
    }
}
