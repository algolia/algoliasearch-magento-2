<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Category\IndexOptionsBuilder as CategoryIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Page\IndexOptionsBuilder as PageIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder as ProductIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexOptionsBuilder as SuggestionIndexOptionsBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class IndicesConfigurator
{
    public function __construct(
        protected Data                          $baseHelper,
        protected IndexOptionsBuilder           $indexOptionsBuilder,
        protected CategoryIndexOptionsBuilder   $categoryIndexOptionsBuilder,
        protected PageIndexOptionsBuilder       $pageIndexOptionsBuilder,
        protected ProductIndexOptionsBuilder    $productIndexOptionsBuilder,
        protected SuggestionIndexOptionsBuilder $suggestionIndexOptionsBuilder,
        protected AlgoliaConnector              $algoliaConnector,
        protected ConfigHelper                  $configHelper,
        protected AutocompleteHelper            $autocompleteHelper,
        protected ProductHelper                 $productHelper,
        protected CategoryHelper                $categoryHelper,
        protected PageHelper                    $pageHelper,
        protected SuggestionHelper              $suggestionHelper,
        protected AdditionalSectionHelper       $additionalSectionHelper,
        protected AlgoliaCredentialsManager     $algoliaCredentialsManager,
        protected IndexSettingsHandler          $indexSettingsHandler,
        protected DiagnosticsLogger             $logger
    ) {}

    /**
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function saveConfigurationToAlgolia(
        int $storeId,
        bool $useTmpIndex = false,
        array $filteredEntities = [])
    : void
    {
        $logEventName = 'Save configuration to Algolia for store: ' . $this->logger->getStoreName($storeId);
        $this->logger->start($logEventName, true);

        if (!$this->algoliaCredentialsManager->checkCredentials($storeId)) {
            $this->logger->log('Algolia credentials are not filled.');
            $this->logger->stop($logEventName, true);

            return;
        }

        if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
            $this->logger->log('Indexing is not enabled for the store.');
            $this->logger->stop($logEventName, true);
            return;
        }

        if (count($filteredEntities) > 0) {
            $this->logger->log('Filtered entities: ' . implode(',', $filteredEntities));

            if (in_array('products', $filteredEntities)) {
                $this->setProductsSettings($storeId, $useTmpIndex);
            }
            if (in_array('categories', $filteredEntities)) {
                $this->setCategoriesSettings($storeId);
            }
            if (in_array('pages', $filteredEntities)) {
                $this->setPagesSettings($storeId);
            }
            if (in_array('suggestions', $filteredEntities)) {
                $this->setQuerySuggestionsSettings($storeId);
            }
            if (in_array('additional_sections', $filteredEntities)) {
                $this->setAdditionalSectionsSettings($storeId);
            }
        } else {
            $this->setAllEntitiesSettings($storeId, $useTmpIndex);
        }

        $this->setExtraSettings($storeId, $useTmpIndex, $filteredEntities);

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     * @throws AlgoliaException
     */
    protected function setAllEntitiesSettings(int $storeId, bool $useTmpIndex = false): void
    {
        $this->setCategoriesSettings($storeId);
        $this->setPagesSettings($storeId);
        $this->setQuerySuggestionsSettings($storeId);
        $this->setAdditionalSectionsSettings($storeId);
        $this->setProductsSettings($storeId, $useTmpIndex);
    }

    protected function logSettingsPush(IndexOptionsInterface $indexOptions, array $settings): void
    {
        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('Settings: ' . json_encode($settings));
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setCategoriesSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for categories indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->categoryHelper->getIndexSettings($storeId);
        $indexOptions = $this->categoryIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush($indexOptions, $settings);
            $this->algoliaConnector->waitLastTask($storeId);
        }

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setPagesSettings(int $storeId): void
    {
        /* Check if we want to index CMS pages */
        if (!$this->configHelper->isPagesIndexEnabled($storeId)) {
            $this->logger->log('CMS Page Indexing is not enabled for the store.');
            return;
        }

        $logEventName = 'Pushing settings for CMS pages indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->pageHelper->getIndexSettings($storeId);
        $indexOptions = $this->pageIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush($indexOptions, $settings);
            $this->algoliaConnector->waitLastTask($storeId);
        }

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setQuerySuggestionsSettings(int $storeId): void
    {
        //Check if we want to index Query Suggestions
        if (!$this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            $this->logger->log('Query Suggestions Indexing is not enabled for the store.');
            return;
        }

        $logEventName = 'Pushing settings for query suggestions indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->suggestionHelper->getIndexSettings($storeId);
        $indexOptions = $this->suggestionIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush($indexOptions, $settings);
            $this->algoliaConnector->waitLastTask($storeId);
        }

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setAdditionalSectionsSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for query suggestions indices.';
        $this->logger->start($logEventName, true);

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($this->autocompleteHelper->getAdditionalSections($storeId) as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];

            $settings = $this->additionalSectionHelper->getIndexSettings($storeId);
            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

            if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
                $this->logSettingsPush($indexOptions, $settings);
                $this->algoliaConnector->waitLastTask($storeId);
            }
        }

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setProductsSettings(int $storeId, bool $useTmpIndex): void
    {
        $logEventName = 'Pushing settings for products indices.';
        $this->logger->start($logEventName, true);

        $indexOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId);
        $indexTmpOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId, true);

        $this->productHelper->setSettings($indexOptions, $indexTmpOptions, $storeId, $useTmpIndex);

        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setExtraSettings(int $storeId, bool $saveToTmpIndicesToo, ?array $filteredEntities = []): void
    {
        $logEventName = 'Pushing extra settings.';
        $this->logger->start($logEventName, true);

        $sections = [
            'products',
            'categories',
            'pages',
            'suggestions',
            'additional_sections'
        ];

        if (count($filteredEntities) > 0) {
            $sections = array_intersect($sections, $filteredEntities);
        }

        $error = [];
        foreach ($sections as $section) {
            try {
                $extraSettings = $this->configHelper->getExtraSettings($section, $storeId);

                if ($extraSettings) {
                    $extraSettings = json_decode($extraSettings, true);
                    $indexOptions = $this->indexOptionsBuilder->buildWithComputedIndex('_' . $section, $storeId);

                    if ($this->indexSettingsHandler->setSettings($indexOptions, $extraSettings)) {
                        $this->logSettingsPush($indexOptions, $extraSettings);
                        $this->algoliaConnector->waitLastTask($storeId);
                    }

                    if ($section === 'products' && $saveToTmpIndicesToo) {
                        $indexTempOptions = $this->indexOptionsBuilder->buildWithComputedIndex(
                            ProductHelper::INDEX_NAME_SUFFIX,
                            $storeId,
                            true
                        );

                        // Direct call to AlgoliaConnector::setSettings() (see ProductHelper::setSettings())
                        $this->algoliaConnector->setSettings(
                            $indexTempOptions,
                            $extraSettings,
                            false,
                            true,
                            $indexOptions->getIndexName()
                        );
                        $this->logSettingsPush($indexTempOptions, $extraSettings);
                        $this->algoliaConnector->waitLastTask($storeId);
                    }
                }
            } catch (AlgoliaException $e) {
                if (mb_strpos($e->getMessage(), 'Invalid object attributes:') === 0) {
                    $error[] = '
                        Extra settings for "' . $section . '" indices were not saved.
                        Error message: "' . $e->getMessage() . '"';

                    continue;
                }

                throw $e;
            }
        }

        if ($error) {
            throw new AlgoliaException('<br>' . implode('<br> ', $error));
        }

        $this->logger->stop($logEventName, true);
    }
}
