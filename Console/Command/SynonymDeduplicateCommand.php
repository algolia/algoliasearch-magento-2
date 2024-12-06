<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynonymDeduplicateCommand extends AbstractStoreCommand
{
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
        $this->output = $output;
        $this->setAreaCode();

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

    public function dedupeSynonyms(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->dedupeSynonymsForStore($storeId);
            }
        } else {
            // handle all
        }
    }

    public function dedupeSynonymsForStore(int $storeId): void
    {
        $this->output->writeln('<info>Deduplicating synonyms for ' . $this->storeNameFetcher->getStoreName($storeId) . '...</info>');
    }



}
