<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\RecordBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BatchingOptimizeCommand extends AbstractStoreCommand
{
    /**
     * Recommended Max batch size
     * https://www.algolia.com/doc/guides/sending-and-managing-data/send-and-update-your-data/how-to/sending-records-in-batches/
     */
    const MAX_BATCH_SIZE_IN_BYTES = 10_000_000; //10MB

    /**
     * Arbitrary default margin to ensure not to exceed recommended batch size
     */
    const DEFAULT_MARGIN = 25;

    /**
     * Arbitrary increased margin to ensure not to exceed recommended batch size when catalog is a mix between complex and other product types
     * (i.e. with a lot of record sizes variations)
     */
    const INCREASED_MARGIN = 50;

    const DEFAULT_SAMPLE_SIZE = 20;
    const LARGE_SAMPLE_SIZE = 100;

    protected const LARGE_SAMPLE_OPTION = 'l-sample';

    protected const LARGE_SAMPLE_OPTION_SHORTCUT = 'l';

    const PRODUCTS_SIMPLE_TYPES = [
        'simple',
        'downloadable',
        'virtual',
        'giftcard'
    ];

    const PRODUCTS_COMPLEX_TYPES = [
        'configurable',
        'grouped',
        'bundle'
    ];

    /**
     * @var array|null
     */
    protected ?array $storeCounts = [];

    public function __construct(
        protected AlgoliaConnector      $algoliaConnector,
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
        protected IndexOptionsBuilder   $indexOptionsBuilder,
        protected ProductHelper         $productHelper,
        protected ConfigHelper          $configHelper,
        protected RecordBuilder         $recordBuilder,
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
        return 'optimize';
    }

    protected function getCommandDescription(): string
    {
        return "Scans some products to determine the average product record size.";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to optimize (optional), if no store is specified, all stores will be taken into account.';
    }

    protected function getAdditionalDefinition(): array
    {
        return [
            new InputOption(
                self::LARGE_SAMPLE_OPTION,
                '-' . self::LARGE_SAMPLE_OPTION_SHORTCUT,
                InputOption::VALUE_NONE,
                'Use a large sample of products (100)'
            )
        ];
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
            $this->scanProductRecords($storeIds);
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param array $storeIds
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function scanProductRecords(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->scanProductRecordsForStore($storeId);
            }
        } else {
            $this->scanProductRecordsForAllStores();
        }
    }

    /**
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function scanProductRecordsForAllStores(): void
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $this->scanProductRecordsForStore($storeId);
        }
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function scanProductRecordsForStore(int $storeId): void
    {
        $storeName = $this->storeNameFetcher->getStoreName($storeId);

        if (!$this->configHelper->isIndexingEnabled($storeId)) {
            $this->output->writeln('<info>Indexing is disabled for store ' . $storeName . '</info>');
            return;
        }

        if (!isset($this->storeCounts[$storeId])) {
            $this->setStoreCounts($storeId);
        }

        $this->output->writeln(' ');
        $this->output->writeln('<info> ====== Products for store ' . $storeName . ' ====== </info>');
        $this->output->writeln('<comment>Simple Products</comment>:  ' . $this->storeCounts[$storeId]['simple'] . ' (' . round($this->storeCounts[$storeId]['simple_percentage'], 2)  . '% of total)');
        $this->output->writeln('<comment>Complex Products</comment>: ' . $this->storeCounts[$storeId]['complex'] . ' (' . round($this->storeCounts[$storeId]['complex_percentage'], 2) . '% of total)');

        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln('<comment>Total</comment>: ' . $this->storeCounts[$storeId]['total'] . ' products');

        $this->output->writeln('<info> ============ </info>');

        $sample = $this->storeCounts[$storeId]['sample'];

        if (count($sample) > 0) {
            $this->output->writeln('<comment>Sample (' . count($sample) . ' products):</comment>');
            foreach ($sample as $sku => $size) {
                $this->output->writeln(' - ' . $size . 'B (sku: ' . $sku . ')');
            }
        }

        $this->output->writeln('<info> ============ </info>');
        $sizeAverage = $this->getSizeAverage($sample);
        $this->output->writeln('<comment>Min record size</comment>             : ' . $this->storeCounts[$storeId]['sample_min'] . 'B');
        $this->output->writeln('<comment>Max record size</comment>             : ' . $this->storeCounts[$storeId]['sample_max'] . 'B');
        $this->output->writeln('<comment>Average record size</comment>         : ' . $sizeAverage . 'B');

        $estimatedBatchCount = $this->getEstimatedMaxBatchCount($sizeAverage);
        $this->output->writeln('<comment>Estimated Max batch count</comment>   : ' . $estimatedBatchCount . ' records');

        $standardDeviation = $this->getStandardDeviation($sample, $sizeAverage);
        $this->output->writeln('<comment>Standard Deviation</comment>          : ' . $standardDeviation);

        $recommendedBatchCountLow = $this->getRecommendedBatchCount($sizeAverage, $standardDeviation, self::INCREASED_MARGIN);
        $recommendedBatchCountHigh = $this->getRecommendedBatchCount($sizeAverage, $standardDeviation);
        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln('<info>Recommended batch count (low)</info>  : ' . $recommendedBatchCountLow . ' records');
        $this->output->writeln('<info>Recommended batch count (high)</info> : ' . $recommendedBatchCountHigh . ' records');
        $this->output->writeln(' ');
        $this->output->writeln(
            'This will override your "Maximum number of records processed per indexing job" configuration to <info>' . $recommendedBatchCountLow . '</info> for store "' . $storeName . '".');

        if ($this->confirmOperation()) {
            $this->configWriter->save(
                ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
                $recommendedBatchCountLow,
                'stores',
                $storeId
            );
        }
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setStoreCounts(int $storeId): void
    {
        $simpleProducts = $this->getProductsCollectionForStore($storeId, self::PRODUCTS_SIMPLE_TYPES);
        $complexProducts = $this->getProductsCollectionForStore($storeId, self::PRODUCTS_COMPLEX_TYPES);

        $this->storeCounts[$storeId] = [
            'simple' => $simpleProducts->count(),
            'complex' => $complexProducts->count()
        ];

        $this->storeCounts[$storeId]['total'] =
            (int) $this->storeCounts[$storeId]['simple'] + (int) $this->storeCounts[$storeId]['complex'];

        $this->storeCounts[$storeId]['simple_percentage'] = $this->storeCounts[$storeId]['total'] > 0 ?
            ($this->storeCounts[$storeId]['simple'] * 100) / $this->storeCounts[$storeId]['total'] :
            0;

        $this->storeCounts[$storeId]['complex_percentage'] = $this->storeCounts[$storeId]['total'] > 0 ?
            ($this->storeCounts[$storeId]['complex'] * 100) / $this->storeCounts[$storeId]['total']:
            0;

        $sampleSize = $this->input->getOption(self::LARGE_SAMPLE_OPTION) ?
            self::LARGE_SAMPLE_SIZE :
            self::DEFAULT_SAMPLE_SIZE;
        $simpleSampleSize = (int)round($sampleSize * ($this->storeCounts[$storeId]['simple_percentage'] / 100));
        $complexSampleSize = (int)round($sampleSize * ($this->storeCounts[$storeId]['complex_percentage'] / 100));

        $this->storeCounts[$storeId]['simple_sample_size'] = $simpleSampleSize;
        $this->storeCounts[$storeId]['complex_sample_size'] = $complexSampleSize;

        $this->storeCounts[$storeId]['sample'] = array_merge(
            $this->getProductsSizes($simpleProducts, $simpleSampleSize),
            $this->getProductsSizes($complexProducts, $complexSampleSize)
        );

        $this->storeCounts[$storeId]['sample_min'] = min($this->storeCounts[$storeId]['sample']);
        $this->storeCounts[$storeId]['sample_max'] = max($this->storeCounts[$storeId]['sample']);
    }

    /**
     * @param int $storeId
     * @param array $productTypes
     * @return Collection
     */
    protected function getProductsCollectionForStore(int $storeId, array $productTypes = []): Collection
    {
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
        $collection = $this->productHelper->getProductCollectionQuery($storeId, null, $onlyVisible);
        if (count($productTypes) > 0) {
            $collection->addAttributeToFilter('type_id', ['in' => $productTypes]);
        }

        // Randomize the results to get a more "diverse" sample
        $collection->getSelect()->orderRand();

        return $collection;
    }

    /**
     * @param Collection $products
     * @param int $sampleSize
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     * @throws AlgoliaException
     */
    protected function getProductsSizes(Collection $products, int $sampleSize): array
    {
        $stats = [];
        $limit = 0;

        foreach ($products as $product) {
            if ($limit >= $sampleSize) {
                break;
            }

            $serializedRecord = json_encode($this->recordBuilder->buildRecord($product));

            if (function_exists('mb_strlen')) {
                $size = mb_strlen($serializedRecord, '8bit');
            } else {
                $size = strlen($serializedRecord);
            }

            $stats[$product->getSku()] = $size;
            $limit++;
        }

        return $stats;
    }

    /**
     * @param array $sizes
     * @return int
     */
    protected function getSizeAverage(array $sizes): int
    {
        if (count($sizes) <= 1) {
            return 0.0;
        }

        return (int) round(array_sum(array_values($sizes)) / count($sizes));
    }

    /**
     * @param int $averageSize
     * @return int
     */
    protected function getEstimatedMaxBatchCount(int $averageSize): int
    {
        return (int) round(self::MAX_BATCH_SIZE_IN_BYTES / $averageSize);
    }

    /**
     * @param array $sizes
     * @param int $averageSize
     * @return float
     */
    protected function getStandardDeviation(array $sizes, int $averageSize): float
    {
        if (count($sizes) <= 1) {
            return 0.0;
        }

        $sum = 0;
        foreach ($sizes as $size) {
            $sum += pow($size - $averageSize, 2);
        }

        return round(sqrt($sum / (count($sizes) - 1)), 2);
    }

    /**
     * @param int $averageSize
     * @param float $standardDeviation
     * @param int $margin
     * @return int
     */
    protected function getRecommendedBatchCount(int $averageSize, float $standardDeviation, int $margin = self::DEFAULT_MARGIN): int
    {
        return (int) (self::MAX_BATCH_SIZE_IN_BYTES / ($averageSize + ($margin/100) * $standardDeviation));
    }
}
