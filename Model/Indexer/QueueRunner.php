<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\Message\ManagerInterface;
use Magento\Indexer\Model\ProcessManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class QueueRunner implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    const INDEXER_ID = 'algolia_queue_runner';

    private $configHelper;
    private $queue;
    private $messageManager;
    private $output;
    private $processManager;
    private $threadsCount;

    public function __construct(
        ConfigHelper $configHelper,
        Queue $queue,
        ManagerInterface $messageManager,
        ConsoleOutput $output,
        ProcessManager $processManager,
        int $threadsCount = 1
    ) {
        $this->configHelper = $configHelper;
        $this->queue = $queue;
        $this->messageManager = $messageManager;
        $this->output = $output;
        $this->processManager = $processManager;
        $this->threadsCount = $threadsCount;
    }

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: 
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $userFunctions = [];
        for ($i = 1; $i <= $this->threadsCount; $i++) {
            $userFunctions[] = function () {
                $this->queue->runCron();
            };
        }

        $this->processManager->execute($userFunctions);
    }

    public function executeList(array $ids)
    {
        return $this;
    }

    public function executeRow($id)
    {
        return $this;
    }
}
