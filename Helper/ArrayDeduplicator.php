<?php

namespace Algolia\AlgoliaSearch\Helper;

class ArrayDeduplicator
{
    /**
     * @param string[] $settingNames
     * @param array<string, array> $settings
     * @return array
     */
    public function dedupeSpecificSettings(array $settingNames, array $settings): array
    {
        $processedSettings = [];
        foreach ($settingNames as $settingName) {
            $processedSettings[$settingName] = isset($settings[$settingName])
                ? $this->dedupeArrayOfArrays($settings[$settingName])
                : null;
        }

        $filteredSettings = [];
        foreach ($processedSettings as $key => $value) {
            if ($value !== null) {
                $filteredSettings[$key] = $value;
            }
        }

        return $filteredSettings;
    }

    /**
     * Find and remove the duplicates in an array of indexed arrays
     * Does not work with associative arrays
     * @param array $data
     * @return array
     */
    public function dedupeArrayOfArrays(array $data): array {
        $encoded = [];
        foreach ($data as $item) {
            $encoded[] = json_encode($item);
        }
        $unique = array_values(array_unique($encoded));
        
        $decoded = [];
        foreach ($unique as $item) {
            $decoded[] = json_decode((string) $item, true);
        }

        return $decoded;
    }
}
