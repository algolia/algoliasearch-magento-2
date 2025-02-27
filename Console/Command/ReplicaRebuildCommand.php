<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Api\Console\ReplicaDeleteCommandInterface;
use Algolia\AlgoliaSearch\Api\Console\ReplicaSyncCommandInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Console\Traits\ReplicaDeleteCommandTrait;
use Algolia\AlgoliaSearch\Console\Traits\ReplicaSyncCommandTrait;
use Algolia\AlgoliaSearch\Exception\ReplicaLimitExceededException;
use Algolia\AlgoliaSearch\Exceptions\BadRequestException;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplicaRebuildCommand
    extends AbstractReplicaCommand
    implements ReplicaSyncCommandInterface, ReplicaDeleteCommandInterface
{
    use ReplicaSyncCommandTrait;
    use ReplicaDeleteCommandTrait;

    public function __construct(
        protected ReplicaManagerInterface $replicaManager,
        protected ProductHelper           $productHelper,
        protected StoreManagerInterface   $storeManager,
        protected ReplicaState            $replicaState,
        AppState                          $appState,
        StoreNameFetcher                  $storeNameFetcher,
        ?string                           $name = null
    )
    {
        parent::__construct($appState, $storeNameFetcher, $name);
    }

    protected function getCommandName(): string
    {
        return 'rebuild';
    }

    protected function getCommandDescription(): string
    {
        return "Delete and rebuild replica configuration for Magento sorting attributes (only run this operation if errors are encountered during regular sync)";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to rebuild replicas (optional), if not specified all store replicas will be rebuilt';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->setAreaCode();

        $storeIds = $this->getStoreIds($input);

        $output->writeln($this->decorateOperationAnnouncementMessage('Rebuilding replicas for {{target}}', $storeIds));

        $this->deleteReplicas($storeIds);
        $this->forceState($storeIds);

        try {
            $this->syncReplicas($storeIds);
        } catch (ReplicaLimitExceededException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            $this->output->writeln('<comment>Reduce the number of sorting attributes that have enabled virtual replicas and try again.</comment>');
            return CLI::RETURN_FAILURE;
        } catch (BadRequestException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            if ($storeIds) {
                $this->output->writeln('<comment>Your Algolia application may contain cris-crossed replicas. Try running "algolia:replicas:rebuild" for all stores to correct this.');
            }
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Force the replica change state to always sync the replica configuration
     * Also serves to avoid latency from Algolia API when reading replica configuration for comparison with local Magento config
     * @param int[] $storeIds
     * @return void
     */
    protected function forceState(array $storeIds): void
    {
        if (!count($storeIds)) {
            $storeIds = array_keys($this->storeManager->getStores());
        }
        foreach ($storeIds as $storeId) {
            $this->replicaState->setChangeState(ReplicaState::REPLICA_STATE_CHANGED, $storeId);
        }
    }

}
