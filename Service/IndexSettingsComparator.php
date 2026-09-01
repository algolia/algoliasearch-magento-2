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
     * @param array|null $algoliaSettings pre-fetched remote settings; when null they are fetched from the connector
     *
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function matches(
        IndexOptionsInterface $indexOptions,
        array $indexSettings,
        ?array $algoliaSettings = null
    ): bool {
        if ($algoliaSettings === null) {
            $algoliaSettings = $this->connector->getSettings($indexOptions);
        }

        [$algoliaSettings, $indexSettings] = $this->reconcileKeys($algoliaSettings, $indexSettings);

        return $this->getSettingsHash($indexSettings) === $this->getSettingsHash($algoliaSettings);
    }

    protected function reconcileKeys(array $remote, array $local): array
    {
        // Existing behaviour: settings Algolia manages but the extension does not
        // are not differences.
        $remote = array_intersect_key($remote, $local);

        // Algolia does not round-trip empty list settings. `customRanking` is
        // omitted from getSettings whenever it is empty, and
        // `unretrievableAttributes` is omitted until it has been set at least
        // once. For these keys "absent remotely" and "empty locally" describe the
        // same index state, so they must compare equal.
        foreach ($local as $key => $value) {
            if ($value === []) {
                if (!array_key_exists($key, $remote)) {
                    unset($local[$key]);
                } else if ($remote[$key] === null)  {
                    $local[$key] = null;
                }
            }
        }

        return [$remote, $local];
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
            throw new AlgoliaException('Invalid JSON: ' . $e->getMessage());
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
