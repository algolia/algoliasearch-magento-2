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
        return array_filter(
            array_combine(
                $settingNames,
                array_map(
                    fn($settingName) => isset($settings[$settingName])
                        ? $this->dedupeArrayOfArrays($settings[$settingName])
                        : null,
                    $settingNames
                )
            ),
            fn($val) => $val !== null
        );
    }

    /**
     * Find and remove the duplicates in an array of indexed arrays
     * Does not work with associative arrays
     * @param array $data
     * @return array
     */
    public function dedupeArrayOfArrays(array $data): array {
        $encoded = array_map('json_encode', $data);
        $unique = array_values(array_unique($encoded));
        $decoded = array_map(
            fn($item) => json_decode((string) $item, true),
            $unique
        );
        return $decoded;
    }
}
