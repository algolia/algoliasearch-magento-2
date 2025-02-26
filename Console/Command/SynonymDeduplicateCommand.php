<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
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
        protected AlgoliaHelper         $algoliaHelper,
        protected IndexNameFetcher      $indexNameFetcher,
        protected State                 $state,
        protected StoreNameFetcher      $storeNameFetcher,
        protected StoreManagerInterface $storeManager,
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
        return "Identify and remove duplicate synonyms in Algolia";
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) containing synonyms to deduplicate in Algolia (optional), if not specified, synonyms for all stores will be deduplicated';
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
        $this->output->writeln('<fg=red>This deduplicate process cannot handle one way synonyms and will remove them altogether!</fg=red>');
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
        $indexName = $this->indexNameFetcher->getProductIndexName($storeId);
        $settings = $this->algoliaHelper->getSettings($indexName);
        $deduped = $this->dedupeSpecificSettings(['synonyms', 'altCorrections'], $settings);

        //bring over as is
        $deduped['placeholders'] = $settings['placeholders'];

        // Updating the synonyms requires a separate endpoint which is not currently not exposed in the PHP API client
        // See https://www.algolia.com/doc/rest-api/search/#tag/Synonyms/operation/saveSynonyms
        // This method will clear and then overwrite ... (does not handle one way synonyms which are not exposed in settings)
        $this->algoliaHelper->clearSynonyms($indexName);
        $this->algoliaHelper->setSettings($indexName, $deduped, false, false);
        $this->algoliaHelper->waitLastTask($indexName);
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
