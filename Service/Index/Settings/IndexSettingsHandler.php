<?php

namespace Algolia\AlgoliaSearch\Service\Index\Settings;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 *  This class will proxy the settings save operations and forward to replicas based on user configuration
 *  excluding attributes which should never be forwarded
 */
class IndexSettingsHandler
{
    /** As replicas are used for sorting we do not want to override these replicas specific configurations */
    protected const FORWARDING_EXCLUDED_ATTRIBUTES = [
        'customRanking',
        'ranking',
    ];

    public function __construct(
        protected AlgoliaConnector        $connector,
        protected ConfigHelper            $config,
        protected IndexSettingsComparator $indexSettingsComparator,
        protected IndexSettingsPreserver  $indexSettingsPreserver,
        protected AlgoliaLogger           $logger,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function setSettings(IndexOptionsInterface $indexOptions, array $indexSettings): bool
    {
        // Fetch the remote settings once and thread them through both the preserver and the comparator.
        $remoteSettings = $this->connector->getSettings($indexOptions);
        if ($this->config->isLoggingEnabled($indexOptions->getStoreId())) {
            $this->logger->info(
                sprintf("Remote settings for store ID: %d (index name: %s) : %s",
                    $indexOptions->getStoreId(),
                    $indexOptions->getIndexName(),
                    json_encode($remoteSettings)
                )
            );
        }

        // Merge back any remotely managed entries (e.g. ingestion-owned attributesForFaceting) so they
        // survive this write. This must run before the comparison so no-op detection sees the final payload.
        $indexSettings = $this->indexSettingsPreserver->preserve($indexSettings, $remoteSettings);

        // Early return if Algolia settings are already the same
        if ($this->indexSettingsComparator->matches($indexOptions, $indexSettings, $remoteSettings)) {
            if ($this->config->isLoggingEnabled($indexOptions->getStoreId())) {
                $this->logger->info(
                    sprintf(
                        'Skipped setSettings (no diff with existing) for store ID: %d (index name: %s)',
                        $indexOptions->getStoreId(),
                        $indexOptions->getIndexName(),
                    )
                );
            }

            return false;
        }

        if (!$this->config->shouldForwardPrimaryIndexSettingsToReplicas($indexOptions->getStoreId())) {
            $this->connector->setSettings(
                $indexOptions,
                $indexSettings,
                false
            );
            $this->connector->collectTaskIdToWaitFor($indexOptions);

            return true;
        }

        // If we should forward to replicas, we need to remove settings which we don't want to send
        // such as customRanking and ranking (managed by each replica separately)
        [$forward, $noForward] = $this->splitSettings($indexSettings);

        // FORWARDED: $settings without excluded attributes
        if ($forward && !$this->indexSettingsComparator->matches($indexOptions, $forward, $remoteSettings)) {
            $this->connector->setSettings(
                $indexOptions,
                $forward,
                true
            );
            $this->connector->collectTaskIdToWaitFor($indexOptions);
        }

        // NOT FORWARDED: array containing excluded attributes only
        if ($noForward && !$this->indexSettingsComparator->matches($indexOptions, $noForward, $remoteSettings)) {
            $this->connector->setSettings(
                $indexOptions,
                $noForward,
                false
            );
            $this->connector->collectTaskIdToWaitFor($indexOptions);
        }

        return true;
    }

    /**
     * Split settings based on whether they should be forwarded to the replicas
     *
     * @return array Tuple of settings (to forward and not to forward)
     */
    protected function splitSettings(array $settings): array
    {
        $excludedKeys = array_flip(self::FORWARDING_EXCLUDED_ATTRIBUTES);

        return [
            array_diff_key($settings, $excludedKeys),
            array_intersect_key($settings, $excludedKeys),
        ];
    }

}
