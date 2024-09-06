<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;

class IndicesConfigurator
{
    public function __construct(
        protected Data $baseHelper,
        protected AlgoliaHelper $algoliaHelper,
        protected ConfigHelper $configHelper,
        protected ProductHelper $productHelper,
        protected CategoryHelper $categoryHelper,
        protected PageHelper $pageHelper,
        protected SuggestionHelper $suggestionHelper,
        protected AdditionalSectionHelper $additionalSectionHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected Logger $logger
    )
    {}

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
        $this->logger->start($logEventName);

        if (!$this->algoliaCredentialsManager->checkCredentials($storeId)) {
            $this->logger->log('Algolia credentials are not filled.');
            $this->logger->stop($logEventName);

            return;
        }

        if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
            $this->logger->log('Indexing is not enabled for the store.');
            $this->logger->stop($logEventName);
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $this->setCategoriesSettings($storeId);
        $this->algoliaHelper->waitLastTask();

        /* heck if we want to index CMS pages */
        if ($this->configHelper->isPagesIndexEnabled($storeId)) {
            $this->setPagesSettings($storeId);
            $this->algoliaHelper->waitLastTask();
        } else {
            $this->logger->log('CMS Page Indexing is not enabled for the store.');
        }

        //Check if we want to index Query Suggestions
        if ($this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            $this->setQuerySuggestionsSettings($storeId);
            $this->algoliaHelper->waitLastTask();
        } else {
            $this->logger->log('Query Suggestions Indexing is not enabled for the store.');
        }

        $this->setAdditionalSectionsSettings($storeId);
        $this->algoliaHelper->waitLastTask();

        $this->setProductsSettings($storeId, $useTmpIndex);
        $this->algoliaHelper->waitLastTask();

        $this->setExtraSettings($storeId, $useTmpIndex);
        $this->algoliaHelper->waitLastTask();

        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    protected function setCategoriesSettings(int $storeId): void
    {
        $this->logger->start('Pushing settings for categories indices.');

        $indexName = $this->categoryHelper->getIndexName($storeId);
        $settings = $this->categoryHelper->getIndexSettings($storeId);

        $this->algoliaHelper->setSettings($indexName, $settings, false, true);

        $this->logger->log('Index name: ' . $indexName);
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop('Pushing settings for categories indices.');
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function setPagesSettings(int $storeId): void
    {
        $this->logger->start('Pushing settings for CMS pages indices.');

        $settings = $this->pageHelper->getIndexSettings($storeId);
        $indexName = $this->pageHelper->getIndexName($storeId);

        $this->algoliaHelper->setSettings($indexName, $settings, false, true);

        $this->logger->log('Index name: ' . $indexName);
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop('Pushing settings for CMS pages indices.');
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException
     */
    protected function setQuerySuggestionsSettings($storeId)
    {
        $this->logger->start('Pushing settings for query suggestions indices.');

        $indexName = $this->suggestionHelper->getIndexName($storeId);
        $settings = $this->suggestionHelper->getIndexSettings($storeId);

        $this->algoliaHelper->setSettings($indexName, $settings, false, true);

        $this->logger->log('Index name: ' . $indexName);
        $this->logger->log('Settings: ' . json_encode($settings));
        $this->logger->stop('Pushing settings for query suggestions indices.');
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException
     */
    protected function setAdditionalSectionsSettings($storeId)
    {
        $this->logger->start('Pushing settings for additional section indices.');

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($this->configHelper->getAutocompleteSections() as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];

            $settings = $this->additionalSectionHelper->getIndexSettings($storeId);

            $this->algoliaHelper->setSettings($indexName, $settings);

            $this->logger->log('Index name: ' . $indexName);
            $this->logger->log('Settings: ' . json_encode($settings));
            $this->logger->log('Pushed settings for "' . $section['name'] . '" section.');
        }

        $this->logger->stop('Pushing settings for additional section indices.');
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
        $this->logger->start('Pushing settings for products indices.');

        $indexName = $this->productHelper->getIndexName($storeId);
        $indexNameTmp = $this->productHelper->getTempIndexName($storeId);

        $this->logger->log('Index name: ' . $indexName);
        $this->logger->log('TMP Index name: ' . $indexNameTmp);

        $this->productHelper->setSettings($indexName, $indexNameTmp, $storeId, $useTmpIndex);

        $this->logger->stop('Pushing settings for products indices.');
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
        $this->logger->start('Pushing extra settings.');

        $sections = [
            'products' => $this->productHelper->getIndexName($storeId),
            'categories' => $this->categoryHelper->getIndexName($storeId),
            'pages' => $this->pageHelper->getIndexName($storeId),
            'suggestions' => $this->suggestionHelper->getIndexName($storeId),
            'additional_sections' => $this->additionalSectionHelper->getIndexName($storeId)
        ];

        $error = [];
        foreach ($sections as $section => $indexName) {
            try {
                $extraSettings = $this->configHelper->getExtraSettings($section, $storeId);

                if ($extraSettings) {
                    $extraSettings = json_decode($extraSettings, true);

                    $this->logger->log('Index name: ' . $indexName);
                    $this->logger->log('Extra settings: ' . json_encode($extraSettings));
                    $this->algoliaHelper->setSettings($indexName, $extraSettings, true);

                    if ($section === 'products' && $saveToTmpIndicesToo) {
                        $tempIndexName = $indexName . IndexNameFetcher::INDEX_TEMP_SUFFIX;
                        $this->logger->log('Index name: ' . $tempIndexName);
                        $this->logger->log('Extra settings: ' . json_encode($extraSettings));

                        $this->algoliaHelper->setSettings($tempIndexName, $extraSettings, true);
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

        $this->logger->stop('Pushing extra settings.');
    }
}
