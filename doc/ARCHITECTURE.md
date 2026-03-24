# Architecture

This document describes the high-level architecture of the Algolia Search & Discovery
extension for Magento 2 (`Algolia_AlgoliaSearch`). If you want to familiarize yourself
with the codebase, you are in the right place.

See also [AGENTS.md](../AGENTS.md) for verification commands, testing, CI/CD, and code style.

## Bird's Eye View

The extension synchronizes Magento 2 catalog data (products, categories, CMS pages,
search suggestions, and additional sections) into Algolia indices, manages sorting
replicas, captures analytics events, and renders client-side search UI. It runs as a
standard Magento module installed via Composer.

There are two main data flows:

**Indexing** moves data from Magento to Algolia. It comes in two flavors:

```
Delta (incremental):
  Catalog/CMS change
    -> mview subscription or Plugin/Observer
    -> executeList($ids) on the Magento indexer
    -> BatchQueueProcessor.handleDeltaIndex()
    -> buildIndexList queue jobs
    -> IndexBuilder -> RecordBuilder -> AlgoliaConnector
    -> records updated in-place on the live index

Full (complete rebuild):
  CLI command (algolia:reindex:*) or executeFull() if enabled
    -> BatchQueueProcessor.handleFullIndex()
    -> buildIndexFull queue jobs
    -> IndexBuilder -> RecordBuilder -> AlgoliaConnector
    -> records written to the live index (default)
       OR to a temporary index (if temp indexing enabled)
    -> IndexMover atomically swaps temp -> production (when temp index used)
```

**Frontend** delivers search configuration from the server to client-side JS:

```
  Admin config (system.xml)
    -> ConfigHelper + specialized helpers
    -> Block/Configuration.php serializes to a JS config object
    -> autocomplete.js, instantsearch.js, insights.js, recommend.js
    -> client-side rendering (no server-side search results)
```

## Codemap

### `Service/`

Business logic for indexing, organized by entity type. Each entity follows a consistent
pattern - search for `IndexBuilder`, `RecordBuilder`, and `BatchQueueProcessor` to find
the implementations for Product, Category, Page, Suggestion, and AdditionalSection.

`AlgoliaConnector` is the single gateway to the Algolia PHP API client. All API calls
flow through it. `IndexSettingsHandler` pushes index configuration and handles replica
setting forwarding. `AbstractIndexBuilder` provides store emulation so records reflect
the correct store context during indexing.

### `Model/`

Magento models, the indexer layer, and the queue system.

`Model/Indexer/` contains 7 Magento indexers (registered in `etc/indexer.xml`): Products,
Categories, Pages, Suggestions, AdditionalSections, QueueRunner, and DeleteProduct. It
also contains three observer-style classes (ProductObserver, CategoryObserver,
PageObserver) that intercept entity save/delete via Magento's plugin system. These are
functionally Magento plugins (interceptors) despite their "Observer" naming and
`Model/Indexer/` location; a future refactor may relocate them under `Plugin/`.

`Queue` is the cron-driven async job queue backed by the `algoliasearch_queue` table.
`Job` defines the handler whitelist (`ALLOWED_HANDLERS`) that restricts which
class/method pairs can be executed from the queue. `IndicesConfigurator` orchestrates
pushing index settings across all entity types and stores.

### `Helper/`

Configuration hub. `ConfigHelper` (~2,100 lines) is the central reader for all admin
settings. Specialized sub-helpers live in `Helper/Configuration/` (AutocompleteHelper,
InstantSearchHelper, QueueHelper, PersonalizationHelper) and entity helpers in
`Helper/Entity/`. `Helper/Adapter/` bridges to Magento's catalog search adapter.

### `Plugin/`

Magento interceptors declared in `etc/di.xml`. These hook into Magento core to trigger
reindexing on catalog changes (StockItemObserver for inventory, CacheCleanProductPlugin)
and to capture analytics data (QuoteItem for conversion tracking).

### `Observer/`

Event observers bound in `etc/events.xml`, `etc/frontend/events.xml`, and
`etc/adminhtml/events.xml`. The `Insights/` sub-directory captures analytics events
(add-to-cart, purchase, wishlist). Admin observers like `SaveSettings` trigger index
settings pushes when configuration changes.

### `Block/`, `ViewModel/`

Frontend rendering. `Block/Configuration.php` is the critical bridge - it serializes all
server-side config into a JS object consumed by every frontend script.
`Block/Instant/` and `Block/Navigation/` handle InstantSearch and faceted navigation.

### `view/frontend/`

Bundled JS libraries: `autocomplete.js`, `instantsearch.js`, `insights.js`, plus
`hooks.js` for extensibility. Minified vendor libraries in `web/js/lib/`. Templates
in `templates/` and layout XML in `layout/`.

### `etc/`

Magento XML configuration. Key files:

- `di.xml` - DI wiring, plugin declarations, proxy definitions
- `indexer.xml` - 7 indexer registrations
- `mview.xml` - materialized view subscriptions for delta indexing (~12 catalog tables)
- `crontab.xml` - queue processing cron job
- `db_schema.xml` - extension tables (see [Database Tables](#database-tables)) plus columns on `quote_item`/`sales_order_item`
- `adminhtml/system.xml` - all admin settings
- `config.xml` - default values
- `events.xml`, `frontend/events.xml`, `adminhtml/events.xml` - observer bindings

### `Console/Command/`

CLI commands organized by concern: `Indexer/` (reindex per entity or all), `Queue/`
(process, clear), `Replica/` (sync, rebuild, delete, disable-virtual), plus
`SynonymDeduplicateCommand` and `BatchingOptimizeCommand`.

### `Api/`

Interface contracts. `Builder/` defines IndexBuilderInterface, RecordBuilderInterface,
and BatchQueueProcessorInterface - the consistent per-entity pattern. `Data/` holds data
transfer object interfaces. `Insights/` and `Product/` define service contracts.

### `Cron/`

`ProcessQueue.php` - the cron entry point that calls `Queue.runCron()`.

### `Exception/`

Domain-specific exceptions that control indexing flow: `ProductDisabledException`,
`ProductOutOfStockException`, `CategoryNotActiveException`,
`ReplicaLimitExceededException`, etc.

## Indexing: Delta vs Full

The extension supports two distinct indexing modes. Understanding the distinction is
critical for anyone working on the indexing pipeline.

### Delta Indexing

Incremental, event-driven. When a product, category, or CMS page changes, one of two
mechanisms detects it:

1. **Materialized views** (`etc/mview.xml`) subscribe to ~12 catalog tables. When the
   indexer is in "Update by Schedule" mode, Magento collects changed entity IDs and
   passes them to `executeList($ids)`.

2. **Plugin/Observer interceptors** (ProductObserver, CategoryObserver, PageObserver)
   fire on entity save/delete and call `reindexRow($id)` directly when the indexer is
   not in scheduled mode.

In both cases, `BatchQueueProcessor.handleDeltaIndex()` enqueues `buildIndexList` jobs
containing only the changed entity IDs. Records are updated in-place on the live index.

### Full Indexing

A complete rebuild of an entire entity index. This is triggered in two ways:

1. **CLI commands** (`algolia:reindex:products`, `algolia:reindex:all`, etc.) - the
   recommended approach.

2. **Magento's `executeFull()`** on the registered indexers - disabled by default
   (see below).

Since v3.17, temporary indexing is optional. When enabled (requires the queue to be
active and the temp index setting turned on via `QueueHelper::useTmpIndex()`), full
indexing builds into a temporary index, then `IndexMover` atomically swaps it into
production so the live index is never in a partial state. When disabled (the default),
records are written directly to the live index during full reindex.

### Why Full Indexing Is Opt-In

Magento's indexer framework exposes `executeFull()` on all registered indexers, and any
process can invoke it - not just Magento core, but also third-party extensions, ERP
integrations, and CLI tools that call `reindexAll`. This happens regardless of whether
the indexer is in "Update on Save" or "Update by Schedule" mode.

Before v3.16, this meant any of these callers could inadvertently trigger expensive full
Algolia reindexes, flooding the queue and starving delta jobs. The queue allocates only
33% of processing capacity to full reindex jobs (`FULL_REINDEX_TO_REALTIME_JOBS_RATIO`
in Queue), so uncontrolled full reindexes degraded freshness of incremental updates.

Since v3.16, each indexer's `executeFull()` has a guard clause (e.g.,
`ConfigHelper::isProductsIndexerEnabled()`). As of v3.18, all guards default to disabled
in `etc/config.xml`. Full reindexing should be an intentional, scheduled operation
invoked via the Algolia CLI commands.

## Database Tables

Defined in `etc/db_schema.xml`:

| Table | Purpose |
|-------|---------|
| `algoliasearch_queue` | Pending indexing jobs |
| `algoliasearch_queue_log` | Per-run execution logs |
| `algoliasearch_queue_archive` | Completed/failed job archive |
| `algoliasearch_landing_page` | Built-in merchandising landing pages (see Cross-Cutting Concerns) |
| `algoliasearch_query` | Search query data for suggestions |

Additionally, `quote_item` and `sales_order_item` gain columns for Insights analytics tracking.

## Queue System

All indexing operations flow through a cron-driven async queue.

Jobs are persisted to `algoliasearch_queue` and processed by `Cron/ProcessQueue.php`.
Each job references a class and method that must appear in `Job::ALLOWED_HANDLERS` - a
hardcoded whitelist that prevents arbitrary code execution if the queue table is
compromised. Execution logs go to `algoliasearch_queue_log`; completed or failed jobs
are archived to `algoliasearch_queue_archive`.

The queue processes full reindex and delta jobs in a mixed ratio
(`FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33`), ensuring delta updates get at least 67%
of each processing cycle.

## Index Architecture

### Multi-Store Scoping

Every index is scoped to a single Magento store. Configuration and optionally credentials
can differ per store.

The indexer layer iterates stores explicitly - search for the store iteration loop in any
`Model/Indexer/` class. `$storeId` is threaded through nearly every Service method.
`AbstractIndexBuilder` handles store emulation so product data (prices, attributes,
visibility) reflects the correct store context.

### Index Naming

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

### Sorting Replicas

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

## Key Decisions

- **Queue-first indexing.** All indexing is async by default. This avoids blocking
  Magento admin operations and allows batch optimization.

- **Opt-in full reindexing.** Magento's `executeFull()` is guarded and disabled by
  default since v3.18. Any process can invoke `reindexAll` on Magento's indexer
  framework, so full reindexing must be intentional via Algolia CLI commands to prevent
  uncontrolled expensive operations.

- **Handler whitelist.** `Job::ALLOWED_HANDLERS` prevents arbitrary code execution from
  the queue. New queue handlers must be registered here or they are silently rejected.

- **Centralized configuration.** All admin settings are read through `ConfigHelper` or
  its sub-helpers. No direct `ScopeConfigInterface` reads elsewhere in the codebase.

- **Entity-based service organization.** Each indexable entity gets its own Service
  sub-directory with a consistent set of classes: IndexBuilder, RecordBuilder,
  BatchQueueProcessor. New entities should follow this pattern.

- **Single API gateway.** All Algolia PHP client usage goes through `AlgoliaConnector`.
  Other classes never instantiate the client directly.

- **Frontend config bridge.** Server-side config is serialized once in
  `Block/Configuration.php`. There is no REST API for frontend config - JS reads from
  a script block injected into the page.

- **Replica forwarding.** Sorting replica settings are forwarded from the primary index
  via `IndexSettingsHandler`, not configured independently. `ReplicaManager` handles the
  replica lifecycle. See [Sorting Replicas](#sorting-replicas) for details.

## Architectural Invariants

These are properties the codebase maintains by convention.

- No direct Algolia API client usage outside `AlgoliaConnector`.
- No direct `ScopeConfigInterface` reads outside `Helper/`.
- No REST/GraphQL endpoints for indexing control - indexing is triggered only through
  Magento's indexer framework, CLI commands, or the admin UI.
- No server-side rendering of search results - all search rendering is client-side JS.
- No cross-store index sharing - each store gets its own set of indices.

## Cross-Cutting Concerns

**Store scoping.** Every public method in the Service layer that touches indices accepts
`$storeId`. Never assume a single-store context.

**Proxy pattern.** Heavy services are wrapped in Magento Proxy classes for lazy loading.
Search for `\Proxy` in `etc/di.xml` to see which.

**Events and extensibility.** The extension fires Magento events at key points in the
indexing pipeline - external modules can observe these to modify records before they're
sent to Algolia. Frontend JS can be extended via `hooks.js`.

**Insights/Analytics.** A parallel subsystem (`Service/Insights/`, `Observer/Insights/`,
`view/frontend/web/js/insights/`) captures click, conversion, and view events. It runs
independently of the indexing pipeline.

**Landing pages.** Built-in merchandising via the `algoliasearch_landing_page` table and
`LandingPageHelper`. This subsystem is under reconsideration and may be deprecated or
reworked in a future release.

## For Contributors

### Module Development Standards

- Follow Magento 2 Coding Standards strictly
- Use Service Contracts for all public APIs
- Dependency Injection instead of `ObjectManager`
- Repository Pattern for data access
- Factory Pattern for object creation
- Plugin Pattern preferred over preferences/rewrites

### Patterns to follow

**Adding a new indexable entity:**
1. Create `Service/{Entity}/IndexBuilder`, `RecordBuilder`, `BatchQueueProcessor`
2. Register the IndexBuilder methods in `Job::ALLOWED_HANDLERS`
3. Add an indexer in `etc/indexer.xml` and mview subscriptions in `etc/mview.xml`
4. Add a guard clause and config flag for full indexing (see existing indexers)

**Adding admin configuration:**
1. Define the field in `etc/adminhtml/system.xml`
2. Add a getter in `ConfigHelper` or the appropriate sub-helper in `Helper/Configuration/`

**Adding queue handlers:**
Register the class and method in `Job::ALLOWED_HANDLERS` or the job will be silently
rejected at execution time.

### Anti-patterns to avoid

- Instantiating the Algolia PHP client directly - use `AlgoliaConnector`.
- Reading config via `ScopeConfigInterface` - use `ConfigHelper` or a sub-helper.
- Hardcoding store IDs or assuming single-store operation.
- Bypassing the queue for indexing operations.