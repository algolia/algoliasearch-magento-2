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
        // Original code - not passing Codacy
        // $decoded = array_map(
        //     fn($item) => json_decode((string) $item, true),
        //     $unique
        // );
        // Experiment 1
        // $decoded = array_map(
        //     'json_decode',
        //     $unique,
        //     array_fill(0, count($unique), true) // force decoding as associative array
        // );

        // Experiment 2
        // $decoded = [];
        // array_walk($unique, function($item) use (&$decoded) {
        //     $decoded[] = json_decode((string) $item, true);
        // });

        // Experiment 3
        $decoded = [];
        foreach ($unique as $item) {
            $decoded[] = json_decode((string) $item, true);
        }

        return $decoded;
    }
}
