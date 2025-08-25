<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessQueueCommand extends Command
{
    protected ?OutputInterface $output = null;

    public function __construct(
        protected State $state,
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function getCommandName(): string
    {
        return 'algolia:reindex:process_queue';
    }

    protected function getCommandDescription(): string
    {
        return 'Process Algolia\'s indexing queue';
    }

    protected function configure(): void
    {
        $this->setName($this->getCommandName())
            ->setDescription($this->getCommandDescription());

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        try {
            $this->state->setAreaCode(Area::AREA_CRONTAB);
        } catch (LocalizedException $e) {
            // Area code is already set - nothing to do - but report regardless
            $this->output->writeln("Unable to set area code due to the following error: " . $e->getMessage());
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return Cli::RETURN_SUCCESS;
        }

        $this->queue->runCron();

        return Cli::RETURN_SUCCESS;
    }
}
