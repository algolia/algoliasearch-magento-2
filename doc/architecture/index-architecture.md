# Index Architecture

No table maps an index to its store or entity: that mapping lives entirely in the name. Store, entity, and whether a replica
is Magento-managed are all recovered by parsing the name that `IndexNameFetcher` builds.

## Multi-Store Scoping

Every index is scoped to a single Magento store. Configuration and optionally credentials
can differ per store.

The indexer layer iterates stores explicitly - search for the store iteration loop in any
`Model/Indexer/` class. `$storeId` is threaded through nearly every Service method.
`AbstractIndexBuilder` handles store emulation so product data (prices, attributes,
visibility) reflects the correct store context.

## Index Naming

Index names follow a consistent convention built by `IndexNameFetcher`:

| Component | Pattern | Example |
|-----------|---------|---------|
| Base | `{prefix}{storeCode}` | `magento_default` |
| Entity index | `{base}_{entitySuffix}` | `magento_default_products` |
| Temp index | `{entityIndex}_tmp` | `magento_default_products_tmp` |
| Query suggestions (Magento) | `{base}_query_suggestions` | `magento_default_query_suggestions` |

The prefix is configured via `ConfigHelper::getIndexPrefix()`.

Starting with v3.18, the extension also supports
[Algolia Query Suggestions](https://www.algolia.com/doc/guides/building-search-ui/ui-and-ux-patterns/query-suggestions/js)
as an alternative to the built-in Magento suggestions index. When the "Use Algolia Query
Suggestions" mode is selected in the autocomplete configuration, the extension references
an index name configured directly in the Algolia dashboard rather than using the
`_query_suggestions` suffix convention. The Magento suggestions mode (which populates from
the `search_query` table) is now deprecated in favor of this approach.

**Replica index naming:**

| Sort type | Pattern | Example |
|-----------|---------|---------|
| Non-price | `{primary}_{attribute}_{direction}` | `magento_default_products_name_desc` |
| Price (default) | `{primary}_price_default_{direction}` | `magento_default_products_price_default_asc` |
| Price (customer group) | `{primary}_price_group_{groupId}_{direction}` | `magento_default_products_price_group_2_asc` |

All Magento-managed replica names are prefixed with the primary index name. This is how
`ReplicaManager` distinguishes Magento replicas from non-Magento replicas (e.g., those
created in the Algolia dashboard or Merchandising Studio).

## Sorting Replicas

Algolia replicas enable sorting on attributes beyond the primary index's default ranking.
The extension manages two types:

- **Virtual replicas** are lightweight references to the primary index where only
  `customRanking` differs. They are more efficient and allow more replicas per index, but
  are subject to a configurable limit (default 20).
- **Standard replicas** are full index copies with a completely replaced ranking
  configuration. They are more resource-intensive in Algolia.

The type is determined per sort attribute in the admin sorting configuration.

**Customer group multiplication.** Most sort attributes produce one replica per direction
(asc/desc) - a straightforward one-to-one mapping. Price sorting is different. When
customer groups are enabled and a price sort is configured, one replica is created for
*each* customer group in the store's website. A single price sort config line produces N
replicas where N is the number of non-excluded customer groups. This one-to-many
relationship can quickly approach the replica limit.

**Ownership boundary.** `ReplicaManager` only manages replicas whose names are prefixed
with the primary index name. During sync, it merges Magento-managed replicas with any
non-Magento replicas before writing the `replicas` setting on the primary index. This
preserves replicas created externally (Algolia dashboard, Merchandising Studio).

**Lifecycle.** Replica config changes are detected via a registry (`ReplicaState`) that
captures before/after state when admin config is saved. On the next product indexing run,
`ReplicaManager` compares the current Magento sorting config to what Algolia has,
calculates a diff, and applies add/update/delete operations. Ranking is configured per
replica type: virtual replicas prepend the sort attribute to `customRanking` inherited
from the primary; standard replicas replace the full `ranking` array.

**Validation.** Replica count is validated against the limit before sync. Price-sort
replicas are tracked separately to detect customer-group-driven overflow. On failure, the
system attempts to revert config to the previous state.

The implementation lives primarily in `Service/Product/` (`ReplicaManager`,
`SortingTransformer`) with change detection in `Registry/ReplicaState` and config hooks
in `Model/Backend/`.

---

See [Architecture](../ARCHITECTURE.md) for full overview.
