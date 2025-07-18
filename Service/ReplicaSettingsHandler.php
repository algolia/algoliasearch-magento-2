<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 *  This class will proxy the settings save operations and forward to replicas based on user configuration
 *  excluding attributes which should never be forwarded
 */
class ReplicaSettingsHandler
{
    /**
     * As replicas are used for sorting we do not want to override these replicas specific configurations
     */
    protected const FORWARDING_EXCLUDED_ATTRIBUTES = [
        'customRanking',
        'ranking'
    ];

    public function __construct(
        protected AlgoliaConnector $connector,
        protected ConfigHelper $config,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function setSettings(
        IndexOptionsInterface $indexOptions,
        array $indexSettings,
        string $mergeSettingsFrom = ''
    ): void
    {
        if ($this->config->shouldForwardPrimaryIndexSettingsToReplicas($indexOptions->getStoreId())) {
            [$forward, $noForward] = $this->splitSettings($indexSettings);
            $this->connector->setSettings(
                $indexOptions,
                $forward,
                true,
                false
            );
            $this->connector->setSettings(
                $indexOptions,
                $noForward,
                false,
                true,
                $mergeSettingsFrom
            );
        } else {
            $this->connector->setSettings(
                $indexOptions,
                $indexSettings,
                false,
                true,
                $mergeSettingsFrom
            );
        }
    }

    /**
     * Split settings based on whether they should be forwarded to the replicas
     * @return array Tuple of settings (to forward and not to forward)
     */
    protected function splitSettings(array $settings): array
    {
        $excludedKeys = array_flip(self::FORWARDING_EXCLUDED_ATTRIBUTES);
        return [
            array_diff_key($settings, $excludedKeys),
            array_intersect_key($settings, $excludedKeys)
        ];
    }

}
