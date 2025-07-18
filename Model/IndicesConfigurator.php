<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
use Algolia\AlgoliaSearch\Service\ReplicaSettingsHandler;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexOptionsBuilder as SuggestionIndexOptionsBuilder;
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
        protected ProductHelper                 $productHelper,
        protected CategoryHelper                $categoryHelper,
        protected PageHelper                    $pageHelper,
        protected SuggestionHelper              $suggestionHelper,
        protected AdditionalSectionHelper       $additionalSectionHelper,
        protected AlgoliaCredentialsManager     $algoliaCredentialsManager,
        protected ReplicaSettingsHandler        $replicaSettingsHandler,
        protected DiagnosticsLogger             $logger
    ) {}

    /**
     * @param int $storeId
     * @param bool $useTmpIndex
     * @return void
     * @throws AlgoliaException
     * @throws \Magento\Framework\Exception\LocalizedException
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
        $this->algoliaConnector->waitLastTask($storeId);

        /* Check if we want to index CMS pages */
        if ($this->configHelper->isPagesIndexEnabled($storeId)) {
            $this->setPagesSettings($storeId);
            $this->algoliaConnector->waitLastTask($storeId);
        } else {
            $this->logger->log('CMS Page Indexing is not enabled for the store.');
        }

        //Check if we want to index Query Suggestions
        if ($this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            $this->setQuerySuggestionsSettings($storeId);
            $this->algoliaConnector->waitLastTask($storeId);
        } else {
            $this->logger->log('Query Suggestions Indexing is not enabled for the store.');
        }

        $this->setAdditionalSectionsSettings($storeId);
        $this->algoliaConnector->waitLastTask($storeId);

        $this->setProductsSettings($storeId, $useTmpIndex);

        $this->setExtraSettings($storeId, $useTmpIndex);

        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    protected function setCategoriesSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for categories indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->categoryHelper->getIndexSettings($storeId);
        $indexOptions = $this->categoryIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        $this->replicaSettingsHandler->setSettings($indexOptions, $settings);

        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    protected function setPagesSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for CMS pages indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->pageHelper->getIndexSettings($storeId);
        $indexOptions = $this->pageIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        $this->replicaSettingsHandler->setSettings($indexOptions, $settings);

        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    protected function setQuerySuggestionsSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for query suggestions indices.';
        $this->logger->start($logEventName, true);

        $settings = $this->suggestionHelper->getIndexSettings($storeId);
        $indexOptions = $this->suggestionIndexOptionsBuilder->buildEntityIndexOptions($storeId);

        $this->replicaSettingsHandler->setSettings($indexOptions, $settings);

        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    protected function setAdditionalSectionsSettings(int $storeId): void
    {
        $logEventName = 'Pushing settings for additional section indices.';
        $this->logger->start($logEventName, true);

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($this->configHelper->getAutocompleteSections() as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];

            $settings = $this->additionalSectionHelper->getIndexSettings($storeId);
            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

            $this->replicaSettingsHandler->setSettings($indexOptions, $settings);

            $this->logger->log('Index name: ' . $indexName);
            $this->logger->log('Settings: ' . json_encode($settings));
            $this->logger->log('Pushed settings for "' . $section['name'] . '" section.');
        }

        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     * @param bool $useTmpIndex
     * @return void
     * @throws AlgoliaException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setProductsSettings(int $storeId, bool $useTmpIndex): void
    {
        $logEventName = 'Pushing settings for products indices.';
        $this->logger->start($logEventName, true);

        $indexOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId);
        $indexTmpOptions = $this->productIndexOptionsBuilder->buildEntityIndexOptions($storeId, true);

        $this->logger->log('Index name: ' . $indexOptions->getIndexName());
        $this->logger->log('TMP Index name: ' . $indexTmpOptions->getIndexName());

        $this->productHelper->setSettings($indexOptions, $indexTmpOptions, $storeId, $useTmpIndex);

        $this->logger->stop($logEventName, true);
    }

    /**
     * @param int $storeId
     * @param bool $saveToTmpIndicesToo
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function setExtraSettings(int $storeId, bool $saveToTmpIndicesToo): void
    {
        $logEventName = 'Pushing extra settings.';
        $this->logger->start($logEventName, true);

        $sections = [
            'products' => $this->productHelper->getIndexName($storeId),
            'categories' => $this->categoryHelper->getIndexName($storeId),
            'pages' => $this->pageHelper->getIndexName($storeId),
            'suggestions' => $this->suggestionHelper->getIndexName($storeId),
            'additional_sections' => $this->additionalSectionHelper->getIndexName($storeId)
        ];
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

                    $this->logger->log('Index name: ' . $indexOptions->getIndexName());
                    $this->logger->log('Extra settings: ' . json_encode($extraSettings));

                    $this->algoliaConnector->setSettings(
                        $indexOptions,
                        $extraSettings,
                        true,
                        false
                    );
                    $this->algoliaConnector->waitLastTask($storeId);

                    if ($section === 'products' && $saveToTmpIndicesToo) {
                        $indexTempOptions = $this->indexOptionsBuilder->buildWithComputedIndex(
                            ProductHelper::INDEX_NAME_SUFFIX,
                            $storeId,
                            true
                        );

                        $this->logger->log('Index name: ' . $indexTempOptions->getIndexName());
                        $this->logger->log('Extra settings: ' . json_encode($extraSettings));

                        $this->algoliaConnector->setSettings(
                            $indexTempOptions,
                            $extraSettings,
                            true
                        );
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
