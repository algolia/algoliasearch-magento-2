<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;

abstract class AbstractIndexerCommand extends AbstractStoreCommand
{
    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'reindex:';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) you want to reindex to Algolia (optional), if not specified, all stores will be reindexed';
    }
}
