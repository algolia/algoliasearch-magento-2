<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexAllCommand extends AbstractIndexerCommand
{
    protected $commandsList = [
        'algolia:reindex:products',
        'algolia:reindex:categories',
        'algolia:reindex:pages',
        'algolia:reindex:suggestions',
        'algolia:reindex:additional_sections',
    ];

    public function __construct(
        protected StoreManagerInterface $storeManager,
        State $state,
        StoreNameFetcher $storeNameFetcher,
        ?string $name = null
    ) {
        parent::__construct($storeManager, $state, $storeNameFetcher, $name);
    }

    protected function getCommandName(): string
    {
        return 'all';
    }

    protected function getCommandDescription(): string
    {
        return 'Reindex all entities to Algolia (if the queue is enabled, indexing will be processed asynchronously)';
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

        $output->writeln('Reindex all entities to Algolia (if the queue is enabled, indexing will be processed asynchronously)');
        if (!$this->confirmOperation()) {
            return CLI::RETURN_SUCCESS;
        }

        foreach ($this->commandsList as $commandName) {
            $command = $this->getApplication()->find($commandName);
            $command->run($input, $output);
        }

        return Cli::RETURN_SUCCESS;
    }
}
