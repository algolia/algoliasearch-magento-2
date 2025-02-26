<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynonymDeduplicateCommand extends AbstractStoreCommand
{
    public function __construct(
        protected AlgoliaConnector      $algoliaConnector,
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
        protected IndexOptionsBuilder   $indexOptionsBuilder,
        ?string                         $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'synonyms:';
    }

    protected function getCommandName(): string
    {
        return 'deduplicate';
    }

    protected function getCommandDescription(): string
    {
        return "Identify and remove duplicate synonyms in Algolia for the following types: synonyms, placeholders and alternative corrections \n  <fg=red>WARNING:</fg=red> If you use one-way synonyms, do not use this command as it will remove these from your index.";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) containing synonyms to deduplicate in Algolia (optional), if not specified, synonyms for all stores will be deduplicated. Pass multiple store IDs as a list of integers separated by spaces. e.g.: <info>bin/magento algolia:synonyms:deduplicate 1 2 3</info>';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->setAreaCode();

        if (!$this->confirmDedupeOperation()) {
            return Cli::RETURN_SUCCESS;
        }

        $storeIds = $this->getStoreIds($input);

        $output->writeln($this->decorateOperationAnnouncementMessage('Deduplicating synonyms for {{target}}', $storeIds));

        try {
            $this->dedupeSynonyms($storeIds);
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return CLI::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Due to limitations in PHP API client at the time of this implementation, one way synonyms cannot be processed
     * and will be removed completely
     * Verify with the end user first!
     *
     * @return bool
     */
    protected function confirmDedupeOperation(): bool
    {
        $this->output->writeln('<fg=red>WARNING:</fg=red> This deduplicate process cannot handle one way synonyms and will remove them altogether!');
        return $this->confirmOperation();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function dedupeSynonyms(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->dedupeSynonymsForStore($storeId);
            }
        } else {
            $this->dedupeSynonymsForAllStores();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function dedupeSynonymsForStore(int $storeId): void
    {
        $this->output->writeln('<info>De-duplicating synonyms for ' . $this->storeNameFetcher->getStoreName($storeId) . '...</info>');

        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
        $settings = $this->algoliaConnector->getSettings($indexOptions);
        $deduped = $this->dedupeSpecificSettings(['synonyms', 'altCorrections'], $settings);

        //bring over as is (no de-dupe necessary)
        if (array_key_exists('placeholders', $settings)) {
            $deduped['placeholders'] = $settings['placeholders'];
        }

        // Updating the synonyms requires a separate endpoint which is not currently not exposed in the PHP API client
        // See https://www.algolia.com/doc/rest-api/search/#tag/Synonyms/operation/saveSynonyms
        // This method will clear and then overwrite ... (does not handle one way synonyms which are not exposed in settings)
        $this->algoliaConnector->clearSynonyms($indexOptions);
        $this->algoliaConnector->setSettings($indexOptions, $deduped, false, false);
        $this->algoliaConnector->waitLastTask($storeId);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function dedupeSynonymsForAllStores(): void
    {
        $storeIds = array_keys($this->storeManager->getStores());
        foreach ($storeIds as $storeId) {
            $this->dedupeSynonymsForStore($storeId);
        }
    }

    /**
     * @param string[] $settingNames
     * @param array<string, array> $settings
     * @return array
     */
    protected function dedupeSpecificSettings(array $settingNames, array $settings): array
    {
        return array_filter(
            array_combine(
                $settingNames,
                array_map(
                    function($settingName) use ($settings) {
                        return isset($settings[$settingName])
                            ? $this->dedupeArrayOfArrays($settings[$settingName])
                            : null;
                    },
                    $settingNames
                )
            ),
            function($val) {
                return $val !== null;
            }
        );
    }

    /**
     * Find and remove the duplicates in an array of indexed arrays
     * Does not work with associative arrays
     * @param array $data
     * @return array
     */
    protected function dedupeArrayOfArrays(array $data): array {
        $encoded = array_map('json_encode', $data);
        $unique = array_values(array_unique($encoded));
        $decoded = array_map(function($item) {
            return json_decode($item, true); },
            $unique
        );
        return $decoded;
    }
}
