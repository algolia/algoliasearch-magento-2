<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexSettingsDiffChecker
{
    public function __construct(
        protected AlgoliaConnector $connector,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function isDifferentFromAlgolia(IndexOptionsInterface $indexOptions, array $indexSettings): bool
    {
        $this->recursiveKSort($indexSettings);

        $algoliaSettings = $this->connector->getSettings($indexOptions);
        $algoliaSettings = array_intersect_key($algoliaSettings, $indexSettings);
        $this->recursiveKSort($algoliaSettings);

        if (hash('sha256', json_encode($indexSettings))!== hash('sha256', json_encode($algoliaSettings))) {
            return true;
        }

        return false;
    }

    protected function recursiveKSort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value))
                $this->recursiveKSort($value);
        }
        ksort($array);
    }
}
