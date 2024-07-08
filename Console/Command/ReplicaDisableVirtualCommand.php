<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Api\Console\ReplicaSyncCommandInterface;
use Algolia\AlgoliaSearch\Console\Traits\ReplicaSyncCommandTrait;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ReplicaDisableVirtualCommand extends AbstractReplicaCommand implements ReplicaSyncCommandInterface
{
    use ReplicaSyncCommandTrait;

    protected function getReplicaCommandName(): string
    {
        return 'disable-virtual-replicas';
    }

    protected function getCommandDescription(): string
    {
        return 'Disable virtual replicas for all product sorting attributes and revert to standard replicas';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to disable virtual replicas (optional), if not specified disable virtual replicas for all stores';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input = $input;
        $this->setAreaCode();

        $storeIds = $this->getStoreIds($input);

        $output->writeln($this->decorateOperationAnnouncementMessage('Disabling virtual replicas for {{target}}', $storeIds));

        $okMsg = 'Configure virtual replicas by attribute under: Stores > Configuration > Algolia Search > InstantSearch Results Page > Sorting';
        if (!$this->confirmOperation($okMsg)) {
            return CLI::RETURN_SUCCESS;
        }

        return Cli::RETURN_SUCCESS;
    }

}