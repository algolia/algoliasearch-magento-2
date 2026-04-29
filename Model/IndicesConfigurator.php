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
    public function saveConfigurationToAlgolia(int $storeId, bool $useTmpIndex = false): void
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

        $this->setCategoriesSettings($storeId);
        $this->setPagesSettings($storeId);
        $this->setQuerySuggestionsSettings($storeId);
        $this->setAdditionalSectionsSettings($storeId);
        $this->setProductsSettings($storeId, $useTmpIndex);
        $this->setExtraSettings($storeId, $useTmpIndex);

        $this->logger->stop($logEventName, true);
    }

    protected function logSettingsPush(string $logEventName, IndexOptionsInterface $indexOptions, array $settings): void
    {
        $this->logger->start($logEventName, true);
        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop($logEventName, true);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setCategoriesSettings(int $storeId): void
    {
        $settings = $this->categoryHelper->getIndexSettings($storeId);
        $indexOptions = $this->categoryIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush('Pushing settings for categories indices.', $indexOptions, $settings);
            $this->algoliaConnector->waitLastTask($storeId);
        }
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

        $settings = $this->pageHelper->getIndexSettings($storeId);
        $indexOptions = $this->pageIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush('Pushing settings for CMS pages indices.', $indexOptions, $settings);
            $this->algoliaConnector->waitLastTask($storeId);
        }
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

        $settings = $this->suggestionHelper->getIndexSettings($storeId);
        $indexOptions = $this->suggestionIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        if ($this->indexSettingsHandler->setSettings($indexOptions, $settings)) {
            $this->logSettingsPush(
                'Pushing settings for query suggestions indices.',
                $indexOptions,
                $settings
            );
            $this->algoliaConnector->waitLastTask($storeId);
        }
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setAdditionalSectionsSettings(int $storeId): void
    {
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
                $this->logSettingsPush(
                    'Pushing settings for additional section "' . $section['name'] . '".',
                    $indexOptions,
                    $settings
                );
                $this->algoliaConnector->waitLastTask($storeId);
            }
        }
    }

    /**
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setProductsSettings(int $storeId, bool $useTmpIndex): void
    {
        $indexOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId);
        $indexTmpOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId, true);

        $this->productHelper->setSettings($indexOptions, $indexTmpOptions, $storeId, $useTmpIndex);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException|DiagnosticsException
     */
    protected function setExtraSettings(int $storeId, bool $saveToTmpIndicesToo): void
    {
        $sections = [
            'products',
            'categories',
            'pages',
            'suggestions',
            'additional_sections'
        ];

        $error = [];
        foreach ($sections as $section) {
            try {
                $extraSettings = $this->configHelper->getExtraSettings($section, $storeId);

                if ($extraSettings) {
                    $extraSettings = json_decode($extraSettings, true);
                    $indexOptions = $this->indexOptionsBuilder->buildWithComputedIndex('_' . $section, $storeId);

                    if ($this->indexSettingsHandler->setSettings($indexOptions, $extraSettings)) {
                        $this->logSettingsPush(
                            'Pushing extra settings',
                            $indexOptions,
                            $extraSettings
                        );
                        $this->algoliaConnector->waitLastTask($storeId);
                    }

                    if ($section === 'products' && $saveToTmpIndicesToo) {
                        $indexTempOptions = $this->indexOptionsBuilder->buildWithComputedIndex(
                            ProductHelper::INDEX_NAME_SUFFIX,
                            $storeId,
                            true
                        );

                        if ($this->indexSettingsHandler->setSettings($indexTempOptions, $extraSettings)) {
                            $this->logSettingsPush(
                                'Pushing extra settings on product temporary index.',
                                $indexTempOptions,
                                $extraSettings
                            );
                            $this->algoliaConnector->waitLastTask($storeId);
                        }
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
    }
}
