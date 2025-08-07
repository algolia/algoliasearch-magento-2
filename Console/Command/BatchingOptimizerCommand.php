<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BatchingOptimizerCommand extends AbstractStoreCommand
{
    /**
     * @var array|null
     */
    protected ?array $indices = null;

    /**
     * @var array|null
     */
    protected ?array $configurablePercentile = [];

    /**
     * Recommended Max batch size
     * https://www.algolia.com/doc/guides/sending-and-managing-data/send-and-update-your-data/how-to/sending-records-in-batches/
     */
    const int MAX_BATCH_SIZE = 10_000_000;

    /**
     * Arbitrary default margin to ensure not to exceed recommended batch size
     */
    const int DEFAULT_MARGIN = 25;

    /**
     * Arbitrary increased margin to ensure not to exceed recommended batch size when catalog is a mix between configurables and other product types
     * (i.e. with a lot of record sizes variations)
     */
    const int INCREASED_MARGIN = 50;

    /**
     * Arbitrary lower boundary where percentile of configurable products is considered "low enough"
     */
    const int CONFIGURABLE_PERCENTILE_LOWER_BOUNDARY = 10;

    /**
     * Arbitrary upper boundary where percentile of configurable products is considered "high enough"
     */
    const int CONFIGURABLE_PERCENTILE_UPPER_BOUNDARY = 90;

    public function __construct(
        protected AlgoliaConnector      $algoliaConnector,
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
        protected IndexOptionsBuilder   $indexOptionsBuilder,
        protected CollectionFactory     $productCollectionFactory,
        ?string                         $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'batching:';
    }

    protected function getCommandName(): string
    {
        return 'optimizer';
    }

    protected function getCommandDescription(): string
    {
        return "Optimizes the batching size configuration under \"Configuration > Algolia Search > Advanced > Indexing Queue > Maximum number of records processed per indexing job\" according to various configurations.";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to optimize (optional), if no store is specified, all stores will be taken into account.';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    /**
     * @throws NoSuchEntityException|LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->setAreaCode();

        $storeIds = $this->getStoreIds($input);

        try {
            $this->optimizeBatchingConfiguration($storeIds);
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param array $storeIds
     * @return void
     */
    protected function optimizeBatchingConfiguration(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->optimizeBatchingForStore($storeId);
            }
        } else {
            $this->optimizeBatchingForAllStores();
        }
    }

    /**
     * @return void
     */
    protected function optimizeBatchingForAllStores(): void
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $this->optimizeBatchingForStore($storeId);
        }
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function optimizeBatchingForStore(int $storeId): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
        $indexData = $this->getIndexData($indexOptions);
        $configurablePercentile = $this->getConfigurablePercentile($indexData['entries'], $storeId);

        $this->output->writeln('<info> ====== ' . $this->storeNameFetcher->getStoreName($storeId) . ' ====== </info>');
        $this->output->writeln('<comment>Index</comment>:               ' . $indexOptions->getIndexName());
        $this->output->writeln('<comment>Number of records</comment>:   ' . $indexData['entries']
            . ' (' . round($configurablePercentile) . '% of configurable products)');
        $this->output->writeln('<comment>Index data size</comment>:     ' . $indexData['dataSize'] . 'B');

        $averageRecordSize = (int)($indexData['dataSize']/$indexData['entries']);
        $this->output->writeln('<comment>Average record size</comment>: ' . $averageRecordSize . 'B');

        $maxBatchCount = (int)(self::MAX_BATCH_SIZE / $averageRecordSize);
        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln('<comment>Estimated max batch count</comment>:    ' . $maxBatchCount . ' objects');

        $recommendedBatchCount = $this->getRecommendedBatchCount($maxBatchCount, $configurablePercentile);
        $this->output->writeln('<comment>Recommended max batch count</comment>:  ' . $recommendedBatchCount . ' objects');

        // @todo : add a prompt to change the value in the Magento configuration (Maximum number of records processed per indexing job)
    }

    /**
     * Returns percentile of configurable products contained in the index
     *
     * @param int $nbProducts
     * @param int $storeId
     * @return float
     */
    protected function getConfigurablePercentile(int $nbProducts, int $storeId): float
    {
        if (! isset($this->configurablePercentile[$storeId])) {
            $collection = $this->productCollectionFactory->create();
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToFilter('type_id', ['eq' => 'configurable']);

            $this->configurablePercentile[$storeId] = $collection->count() * 100 / $nbProducts;
        }

        return $this->configurablePercentile[$storeId];
    }

    /**
     * Fetches index data from the Algolia Dashboard
     *
     * @param IndexOptions $indexOptions
     * @return array
     * @throws AlgoliaException
     */
    protected function getIndexData(IndexOptions $indexOptions): array
    {
        if ($this->indices === null) {
            $this->indices = $this->algoliaConnector->listIndexes();
        }

        foreach ($this->indices['items'] as $index) {
            if ($index['name'] === $indexOptions->getIndexName()) {
                return $index;
            }
        }

        throw new AlgoliaException('Index does not exist');
    }

    /**
     * Calculates the recommended batch count according to:
     *  - the average record size
     *  - the max batch count
     *  - the percentile of configurable products (<10% and >90% are considered as "steady" so the margin is lower)
     *
     * @param int $maxBatchCount
     * @param float $configurablePercentile
     * @return int
     */
    protected function getRecommendedBatchCount(int $maxBatchCount, float $configurablePercentile): int
    {
        $margin = $configurablePercentile > self::CONFIGURABLE_PERCENTILE_UPPER_BOUNDARY
            || $configurablePercentile < self::CONFIGURABLE_PERCENTILE_LOWER_BOUNDARY ?
            self::DEFAULT_MARGIN :
            self::INCREASED_MARGIN;

        $recommendedBatchCount = (int) ($maxBatchCount * (1 - ($margin / 100)));

        if ($recommendedBatchCount >= 1000) {
            $recommendedBatchCount = floor($recommendedBatchCount / 1000) * 1000;
        } else {
            $length = strlen(floor($recommendedBatchCount));
            $times = str_pad('1', $length, "0");
            $recommendedBatchCount = floor($recommendedBatchCount / $times) * $times;
        }

        return $recommendedBatchCount;
    }
}
