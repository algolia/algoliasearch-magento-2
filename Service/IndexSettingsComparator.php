<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexSettingsComparator
{
    public function __construct(
        protected AlgoliaConnector $connector,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function matches(IndexOptionsInterface $indexOptions, array $indexSettings): bool
    {
        $algoliaSettings = $this->connector->getSettings($indexOptions);
        $algoliaSettings = array_intersect_key($algoliaSettings, $indexSettings);

        if ($this->getSettingsHash($indexSettings) === $this->getSettingsHash($algoliaSettings)) {
            return true;
        }

        return false;
    }

    /**
     * @throws AlgoliaException
     */
    protected function getSettingsHash(array $settings): string
    {
        $this->normalize($settings);

        try {
            $jsonSettings = json_encode($settings, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AlgoliaException('Invalid JSON : ' . $e->getMessage());
        }

        return hash('sha256', $jsonSettings);
    }


    /**
     * Normalize the setting array by recursively sorting all the keys to ensure accurate comparison
     */
    protected function normalize(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value))
                $this->normalize($value);
        }
        ksort($array);
    }
}
