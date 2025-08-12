<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BatchingOptimizerCommand extends AbstractStoreCommand
{
    use BatchingCommandTrait;
    /**
     * @var array|null
     */
    protected ?array $indices = [];

    /**
     * @var array|null
     */
    protected ?array $complexPercentile = [];

    /**
     * Arbitrary lower boundary where percentile of complex products is considered "low enough"
     */
    const COMPLEX_PERCENTILE_LOWER_BOUNDARY = 10;

    /**
     * Arbitrary upper boundary where percentile of complex products is considered "high enough"
     */
    const COMPLEX_PERCENTILE_UPPER_BOUNDARY = 90;

    public function __construct(
        protected AlgoliaConnector      $algoliaConnector,
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
        protected IndexOptionsBuilder   $indexOptionsBuilder,
        protected ProductHelper         $productHelper,
        protected ConfigHelper          $configHelper,
        protected WriterInterface       $configWriter,
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
        $indexData = $this->getIndexData($indexOptions, $storeId);
        $complexPercentile = $this->getComplexPercentile($indexData['entries'], $storeId);

        $this->output->writeln('<info> ====== ' . $this->storeNameFetcher->getStoreName($storeId) . ' ====== </info>');
        $this->output->writeln('<comment>Index</comment>:               ' . $indexOptions->getIndexName());
        $this->output->writeln('<comment>Number of records</comment>:   ' . $indexData['entries']
            . ' (' . round($complexPercentile) . '% of complex products)');
        $this->output->writeln('<comment>Index data size</comment>:     ' . $indexData['dataSize'] . 'B');

        $averageRecordSize = (int)($indexData['dataSize']/$indexData['entries']);
        $this->output->writeln('<comment>Average record size</comment>: ' . $averageRecordSize . 'B');

        $maxBatchCount = (int)(self::MAX_BATCH_SIZE / $averageRecordSize);
        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln('<comment>Estimated max batch count</comment>:    ' . $maxBatchCount . ' objects');

        $recommendedBatchCount = $this->getRecommendedBatchCount($maxBatchCount, $complexPercentile);
        $this->output->writeln('<comment>Recommended max batch count</comment>:  ' . $recommendedBatchCount . ' objects');

        if ($this->confirmOperation()) {
            $this->configWriter->save(
                ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
                $recommendedBatchCount,
                'stores',
                $storeId
            );
        }
    }

    /**
     * Returns percentile of complex products (configurable, bundle, grouped) contained in the index
     *
     * @param int $nbProducts
     * @param int $storeId
     * @return float
     */
    protected function getComplexPercentile(int $nbProducts, int $storeId): float
    {
        if (! isset($this->complexPercentile[$storeId])) {
            $this->complexPercentile[$storeId] =
                $this->getProductsCollectionForStore(
                    $storeId,
                    self::PRODUCTS_COMPLEX_TYPES)
                    ->count() * 100 / $nbProducts;
        }

        return $this->complexPercentile[$storeId];
    }

    /**
     * Fetches index data from the Algolia Dashboard
     *
     * @param IndexOptionsInterface $indexOptions
     * @return array
     * @throws AlgoliaException
     */
    protected function getIndexData(IndexOptionsInterface $indexOptions, int $storeId): array
    {
        if (!isset($this->indices[$storeId])) {
            $this->indices[$storeId] = $this->algoliaConnector->listIndexes($storeId);
        }

        foreach ($this->indices[$storeId]['items'] as $index) {
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
     *  - the percentile of complex products (<10% and >90% are considered as "steady" so the margin is lower)
     *
     * @param int $maxBatchCount
     * @param float $complexPercentile
     * @return int
     */
    protected function getRecommendedBatchCount(int $maxBatchCount, float $complexPercentile): int
    {
        $margin = $complexPercentile > self::COMPLEX_PERCENTILE_UPPER_BOUNDARY
            || $complexPercentile < self::COMPLEX_PERCENTILE_LOWER_BOUNDARY ?
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
