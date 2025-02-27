<?php

namespace Algolia\AlgoliaSearch\Console\Command;

abstract class AbstractReplicaCommand extends AbstractStoreCommand
{
    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'replicas:';
    }

}
