<?php

namespace Algolia\AlgoliaSearch\Service\Index\Settings;

use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;

/**
 * Merges remote setting entries that are managed outside of Magento (for example the internal
 * `attributesForFaceting` entries written by the Algolia ingestion pipeline) back into the payload
 * Magento is about to push, so they are not clobbered on every reindex.
 *
 * Configured by a map of `settingKey => PCRE pattern`. For each configured key present in the proposed
 * payload, remote entries matching the pattern are preserved (remote wins).
 */
class IndexSettingsPreserver
{
    /**
     * Default preservation rules: setting key => PCRE pattern applied to each entry.
     *
     * The `attributesForFaceting` pattern matches a leading underscore, both bare (`_foo`) and inside a
     * `FacetBuilder` decorator (`searchable(_foo)`, `filterOnly(_foo)`).
     *
     * @var array<string, string>
     */
    public const DEFAULT_RULES = [
        'attributesForFaceting' => '/(?:^|\()_/',
    ];

    /**
     * @param array<string, string> $rules setting key => PCRE pattern
     */
    public function __construct(
        protected AlgoliaLogger $logger,
        protected array         $rules = self::DEFAULT_RULES,
    ) {}

    /**
     * Merge remote entries matching the configured preservation rules into the proposed payload.
     *
     * Pure transformation: both the proposed payload and the remote payload are supplied by the caller,
     * so this performs no I/O. Keys not covered by a rule (or absent from the proposed payload) pass
     * through unchanged.
     */
    public function preserve(array $proposed, array $remote): array
    {
        foreach ($this->rules as $key => $pattern) {
            if (!array_key_exists($key, $proposed)) {
                continue;
            }

            $proposedValues = is_array($proposed[$key]) ? $proposed[$key] : [];
            $remoteValues = (isset($remote[$key]) && is_array($remote[$key])) ? $remote[$key] : [];

            $preserved = array_filter(
                $remoteValues,
                fn($entry): bool => is_string($entry) && preg_match($pattern, $entry) === 1
            );

            // Remote wins: a protected entry should never originate from Magento, so drop any that does
            // (and surface it) before merging the authoritative remote entries back in.
            $proposedValues = array_filter(
                $proposedValues,
                function ($entry) use ($pattern, $key): bool {
                    if (is_string($entry) && preg_match($pattern, $entry) === 1) {
                        $this->logger->warning(
                            sprintf(
                                'IndexSettingsPreserver: dropping locally proposed protected entry "%s" for '
                                . 'setting "%s"; the remote value is preserved instead.',
                                $entry,
                                $key
                            )
                        );

                        return false;
                    }

                    return true;
                }
            );

            $proposed[$key] = array_values(array_unique([...$proposedValues, ...$preserved]));
        }

        return $proposed;
    }
}
