<?php

namespace Algolia\AlgoliaSearch\Service\AdditionalSection;

use Algolia\AlgoliaSearch\Api\Builder\IndexBuilderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder implements IndexBuilderInterface
{
    public function __construct(
        protected ConfigHelper            $configHelper,
        protected DiagnosticsLogger       $logger,
        protected Emulation               $emulation,
        protected ScopeCodeResolver       $scopeCodeResolver,
        protected AlgoliaConnector        $algoliaConnector,
        protected IndexOptionsBuilder     $indexOptionsBuilder,
        protected AdditionalSectionHelper $additionalSectionHelper,
    ){
        parent::__construct(
            $configHelper,
            $logger,
            $emulation,
            $scopeCodeResolver,
            $algoliaConnector,
            $indexOptionsBuilder
        );
    }

    /**
     * @param int $storeId
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndexFull(int $storeId, array $options = null): void
    {
        $this->buildIndex($storeId, null, null);
    }

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndex(int $storeId, ?array $entityIds, ?array $options): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $additionalSections = $this->configHelper->getAutocompleteSections();

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($additionalSections as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];
            $tempIndexName = $indexName . IndexNameFetcher::INDEX_TEMP_SUFFIX;

            $attributeValues = $this->additionalSectionHelper->getAttributeValues($storeId, $section);

            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);
            $tempIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($tempIndexName, $storeId);

            foreach (array_chunk($attributeValues, 100) as $chunk) {
                $this->saveObjects($chunk, $tempIndexOptions);
            }

            $this->algoliaConnector->copyQueryRules($indexOptions, $tempIndexOptions);
            $this->algoliaConnector->moveIndex($tempIndexOptions, $indexOptions);

            $this->algoliaConnector->setSettings(
                $indexOptions,
                $this->additionalSectionHelper->getIndexSettings($storeId)
            );
        }
    }
}
