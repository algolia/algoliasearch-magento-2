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

class IndicesConfigurator
{
    /** @var Data */
    private $baseHelper;

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var ProductHelper */
    private $productHelper;

    /** @var CategoryHelper */
    private $categoryHelper;

    /** @var PageHelper */
    private $pageHelper;

    /** @var SuggestionHelper */
    private $suggestionHelper;

    /** @var AdditionalSectionHelper */
    private $additionalSectionHelper;

    /** @var Logger */
    private $logger;

    public function __construct(
        Data $baseHelper,
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        ProductHelper $productHelper,
        CategoryHelper $categoryHelper,
        PageHelper $pageHelper,
        SuggestionHelper $suggestionHelper,
        AdditionalSectionHelper $additionalSectionHelper,
        Logger $logger
    ) {
        $this->baseHelper = $baseHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->configHelper = $configHelper;
        $this->productHelper = $productHelper;
        $this->categoryHelper = $categoryHelper;
        $this->pageHelper = $pageHelper;
        $this->suggestionHelper = $suggestionHelper;
        $this->additionalSectionHelper = $additionalSectionHelper;
        $this->logger = $logger;
    }

    /**
     * @param int $storeId
     * @param bool $useTmpIndex
     *
     * @throws AlgoliaException
     */
    public function saveConfigurationToAlgolia($storeId, $useTmpIndex = false)
    {
        $logEventName = 'Save configuration to Algolia for store: ' . $this->logger->getStoreName($storeId);
        $this->logger->start($logEventName);

        if (!($this->configHelper->getApplicationID() && $this->configHelper->getAPIKey())) {
            $this->logger->log('Algolia credentials are not filled.');
            $this->logger->stop($logEventName);

            return;
        }

        if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
            $this->logger->log('Indexing is not enabled for the store.');
            $this->logger->stop($logEventName);

            return;
        }

        $this->setCategoriesSettings($storeId);
        $this->setPagesSettings($storeId);
        $this->setQuerySuggestionsSettings($storeId);
        $this->setAdditionalSectionsSettings($storeId);
        $this->setProductsSettings($storeId, $useTmpIndex);

        $this->setExtraSettings($storeId, $useTmpIndex);
    }

    /**
     * @param int $storeId
     *
     * @throws AlgoliaException
     */
    private function setCategoriesSettings($storeId)
    {
        $this->logger->start('Pushing settings for categories indices.');

        $indexName = $this->baseHelper->getIndexName($this->categoryHelper->getIndexNameSuffix(), $storeId);
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
     */
    private function setPagesSettings($storeId)
    {
        $this->logger->start('Pushing settings for CMS pages indices.');

        $settings = $this->pageHelper->getIndexSettings($storeId);
        $indexName = $this->baseHelper->getIndexName($this->pageHelper->getIndexNameSuffix(), $storeId);

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
    private function setQuerySuggestionsSettings($storeId)
    {
        $this->logger->start('Pushing settings for query suggestions indices.');

        $indexName = $this->baseHelper->getIndexName($this->suggestionHelper->getIndexNameSuffix(), $storeId);
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
    private function setAdditionalSectionsSettings($storeId)
    {
        $this->logger->start('Pushing settings for additional section indices.');

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($this->configHelper->getAutocompleteSections() as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->baseHelper->getIndexName($this->additionalSectionHelper->getIndexNameSuffix(), $storeId);
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
     *
     * @throws AlgoliaException
     */
    private function setProductsSettings($storeId, $useTmpIndex)
    {
        $this->logger->start('Pushing settings for products indices.');

        $indexName = $this->baseHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
        $indexNameTmp = $this->baseHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId, true);

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
     */
    private function setExtraSettings($storeId, $saveToTmpIndicesToo)
    {
        $this->logger->start('Pushing extra settings.');

        $additionalSectionsSuffix = $this->additionalSectionHelper->getIndexNameSuffix();

        $sections = [
            'products' => $this->baseHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId),
            'categories' => $this->baseHelper->getIndexName($this->categoryHelper->getIndexNameSuffix(), $storeId),
            'pages' => $this->baseHelper->getIndexName($this->pageHelper->getIndexNameSuffix(), $storeId),
            'suggestions' => $this->baseHelper->getIndexName($this->suggestionHelper->getIndexNameSuffix(), $storeId),
            'additional_sections' => $this->baseHelper->getIndexName($additionalSectionsSuffix, $storeId),
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

                    if ($section === 'products' && $saveToTmpIndicesToo === true) {
                        $this->logger->log('Index name: ' . $indexName . '_tmp');
                        $this->logger->log('Extra settings: ' . json_encode($extraSettings));

                        $this->algoliaHelper->setSettings($indexName . '_tmp', $extraSettings, true);
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
