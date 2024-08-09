<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Api\Console\ReplicaDeleteCommandInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Console\Traits\ReplicaDeleteCommandTrait;
use Algolia\AlgoliaSearch\Exceptions\BadRequestException;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ReplicaDeleteCommand extends AbstractReplicaCommand implements ReplicaDeleteCommandInterface
{
    use ReplicaDeleteCommandTrait;

    protected const UNUSED_OPTION = 'unused';
    protected const UNUSED_OPTION_SHORTCUT = 'u';

    public function __construct(
        protected ReplicaManagerInterface\Proxy $replicaManager,
        protected StoreManagerInterface         $storeManager,
        State                                   $state,
        StoreNameFetcher                        $storeNameFetcher,
        ?string                                 $name = null
    )
    {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getReplicaCommandName(): string
    {
        return 'delete';
    }

    protected function getCommandDescription(): string
    {
        return 'Delete associated replica indices in Algolia';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to delete replicas in Algolia (optional), if not specified, replicas for all stores will be deleted';
    }

    protected function getAdditionalDefinition(): array
    {
        return [
            new InputOption(
                self::UNUSED_OPTION,
                '-' . self::UNUSED_OPTION_SHORTCUT,
                InputOption::VALUE_NONE,
                'Delete unused replicas only'
            )
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input = $input;

        $storeIds = $this->getStoreIds($input);
        $unused = $input->getOption(self::UNUSED_OPTION);

        $output->writeln(
            $this->decorateOperationAnnouncementMessage(
                'Deleting' . ($unused ? ' unused ' : ' ') . 'replicas for {{target}}',
                $storeIds
            )
        );

        if ($unused) {
            $unusedReplicas = $this->getUnusedReplicas($storeIds);
            if (!$unusedReplicas) {
                $output->writeln('<comment>No unused replicas found.</comment>');
                return Cli::RETURN_SUCCESS;
            }
            if (!$this->confirmDeleteUnused($unusedReplicas)) {
                return Cli::RETURN_SUCCESS;
            }
        } else if (!$this->confirmDelete()) {
            return Cli::RETURN_SUCCESS;
        }

        try {
            $this->deleteReplicas($storeIds, $unused);
        } catch (BadRequestException $e) {
            $this->output->writeln("<error>Error encountered while attempting to delete replica: {$e->getMessage()}</error>");
            $this->output->writeln('<comment>It is likely that the Magento integration does not manage the index. You should review your application configuration in Algolia.</comment>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param int[] $storeIds
     * @return string[]
     */
    protected function getUnusedReplicas(array $storeIds): array
    {
        return array_reduce(
            $storeIds,
            function ($allUnused, $storeId) {
                $unused = [];
                try {
                    $unused = $this->replicaManager->getUnusedReplicaIndices($storeId);
                } catch (\Exception $e) {
                    $this->output->writeln("<error>Unable to retrieve unused replicas for $storeId: {$e->getMessage()}</error>");
                }
                return array_unique(array_merge($allUnused, $unused));
            },
            []
        );
    }

    /**
     * Deleting unused replica indices is potentially risky, especially if they have enabled query suggestions on their index
     * Verify with the end user first!
     *
     * @param string[] $unusedReplicas
     * @return bool
     */
    protected function confirmDeleteUnused(array $unusedReplicas): bool
    {
        $this->output->writeln('<info>The following replicas appear to be unused and will be deleted:</info>');
        foreach ($unusedReplicas as $unusedReplica) {
            $this->output->writeln('<info> - ' . $unusedReplica . '</info>');
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<question>Do you want to proceed? (y/n)</question> ', false);

        if (!$helper->ask($this->input, $this->output, $question)) {
            $this->output->writeln('<comment>Operation cancelled.</comment>');
            return false;
        }
        return true;
    }

    protected function confirmDelete(): bool
    {
        $okMsg = 'Please note that you can restore these deleted replicas by running "algolia:replicas:sync".';
        return $this->confirmOperation($okMsg);
    }

}
