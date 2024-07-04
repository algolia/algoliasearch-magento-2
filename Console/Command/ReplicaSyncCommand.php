<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exception\ReplicaLimitExceededException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\BadRequestException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplicaSyncCommand extends Command
{
    protected const STORE_ARGUMENT = 'store';

    protected ?OutputInterface $output = null;

    public function __construct(
        protected State                   $state,
        protected ProductHelper           $productHelper,
        protected ReplicaManagerInterface $replicaManager,
        protected StoreManagerInterface   $storeManager,
        protected StoreNameFetcher        $storeNameFetcher,
        ?string                           $name = null
    )
    {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('algolia:replicas:sync')
            ->setDescription('Sync configured sorting attributes in Magento to Algolia replica indices')
            ->setDefinition([
                new InputArgument(
                    self::STORE_ARGUMENT,
                    InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                    'ID(s) for store(s) to be synced with Algolia (optional), if not specified all stores will be synced'
                )
            ]);

        parent::configure();
    }

    /**
     * @inheritDoc
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->setAreaCode();

        $storeIds = (array) $input->getArgument(self::STORE_ARGUMENT);

        $msg = 'Syncing replicas for ' . ($storeIds ? count($storeIds) : 'all') . ' store' . (!$storeIds || count($storeIds) > 1 ? 's' : '');
        if ($storeIds) {
            $output->writeln("<info>$msg: " . join(", ", $this->storeNameFetcher->getStoreNames($storeIds)) . '</info>');
        } else {
            $output->writeln("<info>$msg</info>");
        }


        try {
            $this->syncReplicas($storeIds);
        } catch (BadRequestException) {
            $this->output->writeln('<comment>You appear to have a corrupted replica configuration in Algolia for your Magento instance.</comment>');
            $this->output->writeln('<comment>Run the "algolia:replicas:rebuild" command to correct this.</comment>');
            return CLI::RETURN_FAILURE;
        } catch (ReplicaLimitExceededException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            $this->output->writeln('<comment>Reduce the number of sorting attributes that have enabled virtual replicas and try again.</comment>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    protected function setAreaCode(): void {
        try {
            $this->state->setAreaCode(Area::AREA_CRONTAB);
        } catch (LocalizedException) {
            // Area code is already set - nothing to do
        }
    }

    /**
     * @param int[] $storeIds
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function syncReplicas(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->syncReplicasForStore($storeId);
            }
        } else {
          $this->syncReplicasForAllStores();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws LocalizedException
     */
    protected function syncReplicasForStore(int $storeId): void
    {
        $this->output->writeln('<info>Syncing ' . $this->storeNameFetcher->getStoreName($storeId) . '...</info>');
        try {
            $this->replicaManager->syncReplicasToAlgolia($storeId, $this->productHelper->getIndexSettings($storeId));
        }
        catch (BadRequestException $e) {
            $this->output->writeln('<error>Failed syncing replicas for store "' . $this->storeNameFetcher->getStoreName($storeId) . '": ' . $e->getMessage() . '</error>');
            throw $e;
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws LocalizedException
     */
    protected function syncReplicasForAllStores(): void
    {
        $storeIds = array_keys($this->storeManager->getStores());
        foreach ($storeIds as $storeId) {
            $this->syncReplicasForStore($storeId);
        }
    }
}