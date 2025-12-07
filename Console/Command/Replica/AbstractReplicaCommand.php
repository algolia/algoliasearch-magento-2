<?php

namespace Algolia\AlgoliaSearch\Console\Command\Replica;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;

abstract class AbstractReplicaCommand extends AbstractStoreCommand
{
    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'replicas:';
    }

}
