<?php

namespace Algolia\AlgoliaSearch\Service\IndexBuilder;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

class AdditionalSection extends AbstractIndexBuilder
{
    public function __construct(
        protected ConfigHelper            $configHelper,
        protected DiagnosticsLogger       $logger,
        protected Emulation               $emulation,
        protected ScopeCodeResolver       $scopeCodeResolver,
        protected AlgoliaHelper           $algoliaHelper,
        protected AdditionalSectionHelper $additionalSectionHelper,
    ){
        parent::__construct($configHelper, $logger, $emulation, $scopeCodeResolver, $algoliaHelper);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function rebuildIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $additionalSections = $this->configHelper->getAutocompleteSections();

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($additionalSections as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];

            $attributeValues = $this->additionalSectionHelper->getAttributeValues($storeId, $section);

            $tempIndexName = $indexName . IndexNameFetcher::INDEX_TEMP_SUFFIX;

            foreach (array_chunk($attributeValues, 100) as $chunk) {
                $this->saveObjects($chunk, $tempIndexName);
            }

            $this->algoliaHelper->copyQueryRules($indexName, $tempIndexName);
            $this->algoliaHelper->moveIndex($tempIndexName, $indexName);

            $this->algoliaHelper->setSettings($indexName, $this->additionalSectionHelper->getIndexSettings($storeId));

            $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
        }
    }
}
