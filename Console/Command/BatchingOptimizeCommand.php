<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\MathHelper;
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
    protected const MAX_BATCH_SIZE_IN_BYTES = 10_000_000; //10MB

    /**
     * Margin to ensure not to exceed maximum batch size when catalog is a mix between various product types
     * (i.e. with a lot of record sizes variations) - can be updated by the --margin option (from 0.25 to 3.00)
     * 0.00 => Lowest possible value (0.00 * standard deviation = 0), the recommended batch size will be almost equal to the strictly calculated maximum batch size
     * 0.25 => Default value (0.25 * standard deviation), the recommended batch size will be close to the strictly calculated maximum batch size
     * 3.00 => Highest possible value (3 * standard deviation), the recommended batch size will be greatly lower than the calculated maximum batch size
     */
    protected const DEFAULT_MARGIN = 0.25;

    /**
     * Min value for safety margin
     */
    protected const MIN_MARGIN = 0;

    /**
     * Max value for safety margin
     */
    protected const MAX_MARGIN = 3;

    /**
     * The sample size if the amount of products fetched to determine the recommended batch size
     * Can be updated by the --sample-size option
     */
    protected const DEFAULT_SAMPLE_SIZE = 20;

    /**
     * Max Sample size
     */
    protected const MAX_SAMPLE_SIZE = 1000;

    protected const OPTION_SAMPLE_SIZE = 'sample-size';
    protected const OPTION_SAMPLE_SIZE_SHORTCUT = 's';

    protected const OPTION_MARGIN = 'margin';
    protected const OPTION_MARGIN_SHORTCUT = 'm';

    /**
     * Simple product types (should generate smaller product records)
     */
    protected const PRODUCTS_SIMPLE_TYPES = [
        'simple',
        'downloadable',
        'virtual',
        'giftcard'
    ];

    /**
     * Complex product types (should generate bigger product records)
     */
    protected const PRODUCTS_COMPLEX_TYPES = [
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
        return "Performs catalog analysis and provides recommendation regarding optimal batching size for product indexing.";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to optimize (optional), if no store is specified, all stores will be taken into account.';
    }

    protected function getAdditionalDefinition(): array
    {
        return [
            new InputOption(
                self::OPTION_SAMPLE_SIZE,
                '-' . self::OPTION_SAMPLE_SIZE_SHORTCUT,
                InputOption::VALUE_REQUIRED,
                'Sample size (number of products) - DEFAULT: ' . self::DEFAULT_SAMPLE_SIZE . ' - MAXIMUM: ' . self::MAX_SAMPLE_SIZE,
            ),
            new InputOption(
                self::OPTION_MARGIN,
                '-' . self::OPTION_MARGIN_SHORTCUT,
                InputOption::VALUE_REQUIRED,
                'Safety margin - DEFAULT: ' . self::DEFAULT_MARGIN . ' - FROM ' . self::MIN_MARGIN . ' TO ' . self::MAX_MARGIN,
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
            $this->validateOptions();
            $this->scanProductRecords($storeIds);
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Ensures sample size and margin options are valid
     *
     * @return void
     * @throws AlgoliaException
     */
    protected function validateOptions(): void
    {
        if (
            $this->input->getOption(self::OPTION_SAMPLE_SIZE)
            && (
                !ctype_digit((string) $this->input->getOption(self::OPTION_SAMPLE_SIZE))
                || (int) $this->input->getOption(self::OPTION_SAMPLE_SIZE) > self::MAX_SAMPLE_SIZE
            )
        ) {
            throw new AlgoliaException("Sample size option should be an integer (maximum 1000)" );
        }

        if (
            $this->input->getOption(self::OPTION_MARGIN)
            && (
                !is_numeric($this->input->getOption(self::OPTION_MARGIN))
                || (float) $this->input->getOption(self::OPTION_MARGIN) > self::MAX_MARGIN
                || (float) $this->input->getOption(self::OPTION_MARGIN) < self::MIN_MARGIN
            )
        ) {
            throw new AlgoliaException("Margin option should be a  decimal value (between 0 and 3)" );
        }
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
            $this->output->writeln('<info>Scanning products for store ' . $storeName . '...</info>');
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
        $sizeAverage = (int) round(MathHelper::getAverage($sample));
        $this->output->writeln('<comment>Min record size</comment>             : ' . $this->storeCounts[$storeId]['sample_min'] . 'B');
        $this->output->writeln('<comment>Max record size</comment>             : ' . $this->storeCounts[$storeId]['sample_max'] . 'B');
        $this->output->writeln('<comment>Average record size</comment>         : ' . $sizeAverage . 'B');

        $estimatedBatchCount = $this->getEstimatedMaxBatchCount($sizeAverage);
        $this->output->writeln('<comment>Estimated Max batch count</comment>   : ' . $estimatedBatchCount . ' records');

        $standardDeviation = MathHelper::getSampleStandardDeviation($sample);
        $this->output->writeln('<comment>Standard Deviation</comment>          : ' . $standardDeviation);

        $margin = $this->input->getOption(self::OPTION_MARGIN) ?? self::DEFAULT_MARGIN;
        $this->output->writeln('<comment>Safety margin</comment>               : ' . $margin);

        $recommendedBatchCount = $this->getRecommendedBatchCount($sizeAverage, $standardDeviation, $margin);
        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln('<info>Recommended batch count</info>     : ' . $recommendedBatchCount . ' records');
        $this->output->writeln(' ');
        $this->output->writeln('<fg=red>Important:</fg=red> Those numbers are estimates only. Indexing activity should be monitored after making changes to ensure batches are not exceeding the recommended size of 10 MB.');
        $this->output->writeln('<info> ============ </info>');
        $this->output->writeln(
            'This will override your "Maximum number of records sent per indexing request" configuration to <info>' . $recommendedBatchCount . '</info> for store "' . $storeName . '".');
        $this->output->writeln(' ');

        if ($this->confirmOperation('Applying optimized batching configuration', 'Batching optimization cancelled - settings were NOT changed', true)) {
            $this->configWriter->save(
                ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
                $recommendedBatchCount,
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
            'simple' => $this->getRawCount($simpleProducts),
            'complex' => $this->getRawCount($complexProducts)
        ];

        $this->storeCounts[$storeId]['total'] =
            (int) $this->storeCounts[$storeId]['simple'] + (int) $this->storeCounts[$storeId]['complex'];

        $this->storeCounts[$storeId]['simple_percentage'] = $this->storeCounts[$storeId]['total'] > 0 ?
            ($this->storeCounts[$storeId]['simple'] * 100) / $this->storeCounts[$storeId]['total'] :
            0;

        $this->storeCounts[$storeId]['complex_percentage'] = $this->storeCounts[$storeId]['total'] > 0 ?
            ($this->storeCounts[$storeId]['complex'] * 100) / $this->storeCounts[$storeId]['total']:
            0;


        $sampleSize = $this->input->getOption(self::OPTION_SAMPLE_SIZE) ?? self::DEFAULT_SAMPLE_SIZE;
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
     * Generates a product collection with the same helper as the product indexer to get the exact amount of expected products in the Algolia index
     *
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
     * Relying on Collection count method will unnecesarily hydrate the collection and consume memory
     * This method will return the count of the collection without hydrating it
     *
     * @param Collection $collection
     * @return int
     */
    protected function getRawCount(Collection $collection): int
    {
        $sql = $collection->getSelect()->__toString();

        $connection = $collection->getConnection();

        $rows = $connection->fetchAll($sql);

        return count($rows);
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

        $products->setPageSize($sampleSize)->setCurPage(1);

        foreach ($products as $product) {
            $serializedRecord = json_encode($this->recordBuilder->buildRecord($product));

            if (function_exists('mb_strlen')) {
                $size = mb_strlen($serializedRecord, '8bit');
            } else {
                $size = strlen($serializedRecord);
            }

            $stats[$product->getSku()] = $size;
        }

        return $stats;
    }

    /**
     * Determines the maximum estimated batch count which will be considered as the upper boundary
     *
     * @param int $averageSize
     * @return int
     */
    protected function getEstimatedMaxBatchCount(int $averageSize): int
    {
        return (int) round(self::MAX_BATCH_SIZE_IN_BYTES / $averageSize);
    }

    /**
     * Provides a recommended batch count according to:
     *  - the average record size provided by the product sample
     *  - the standard deviation of the product sample
     *  - an arbitrary safety margin (1 to 10) to allow the user to alter the strictness of the recommendation
     *    (the lower the margin is, the closer it will be from the maximum batch count)
     *
     * @param int $averageSize
     * @param float $standardDeviation
     * @param float $margin
     * @return int
     */
    protected function getRecommendedBatchCount(int $averageSize, float $standardDeviation, float $margin = self::DEFAULT_MARGIN): int
    {
        return (int) (self::MAX_BATCH_SIZE_IN_BYTES / ($averageSize + $margin * $standardDeviation));
    }
}
