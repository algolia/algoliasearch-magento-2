# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Algolia Search & Discovery extension for Magento 2 (`Algolia_AlgoliaSearch`). Provides autocomplete, InstantSearch results pages, faceted navigation, and Algolia Recommend integration. Uses the Algolia PHP API Client v4.18.3.

**Requirements:** PHP 8.2-8.4, Magento 2.4.6+, `algolia/algoliasearch-client-php` 4.18.3

## Common Commands

### Quality Tools (preferred — used by CI)

Install: `composer global require algolia/magento2-tools`

- **`magento2-lint <path>`** — Run PHP-CS-Fixer and auto-fix issues
- **`magento2-types <path>`** — Run PHPStan (level 1)
- **`magento2-php-compatibility <path>`** — Check PHP version compatibility
- **`magento2-test <path>`** — Run all above in dry-run mode

### Unit Tests

Run within a Magento Docker environment (uses `bin/cli`):
```bash
bin/cli vendor/bin/phpunit -c /var/www/html/dev/tests/unit/phpunit.xml.dist \
  /var/www/html/vendor/algolia/algoliasearch-magento-2/Test/Unit
```

### Integration Tests

Requires Algolia credentials as env vars (`ALGOLIA_APPLICATION_ID`, `ALGOLIA_SEARCH_API_KEY`, `ALGOLIA_API_KEY`, optional `INDEX_PREFIX`):
```bash
cd <magento_root>/dev/tests/integration
../../../vendor/bin/phpunit ../../../vendor/algolia/algoliasearch-magento-2/Test/Integration/
```

### Static Analysis (MEQP2)

```bash
phpcs --runtime-set ignore_warnings_on_exit true --ignore=dev,Test <extension_path> --standard=MEQP2 --extensions=php,phtml
```

## Architecture

### Namespace & Registration

PSR-4 root: `Algolia\AlgoliaSearch` (registered in `registration.php`). Module name: `Algolia_AlgoliaSearch`.

### Core Layers

- **Service/** — Business logic for indexing. Key classes:
  - `AlgoliaConnector` — Low-level Algolia API wrapper
  - `Product/IndexBuilder`, `Category/IndexBuilder` — Build index records
  - `Product/RecordBuilder`, `Product/FacetBuilder` — Construct product records and facet configs
  - `Product/ReplicaManager` — Manage sorting replicas
  - `Product/BatchQueueProcessor`, `Category/BatchQueueProcessor` — Batch indexing
  - `IndexSettingsHandler` — Push index settings to Algolia
  - `Insights/EventProcessor` — Analytics event handling

- **Model/** — Magento models, indexers, and queue system:
  - `Queue` — Async job queue (database-backed, `algoliasearch_queue` table)
  - `Indexer/` — 7 indexers: Products, Categories, Pages, Suggestions, AdditionalSections, QueueRunner, DeleteProduct
  - `IndicesConfigurator` — Orchestrates index settings across all entity types
  - `Observer/` — Respond to catalog/CMS save/delete events to trigger reindexing

- **Helper/** — Configuration and utilities:
  - `ConfigHelper` (78KB) — Central config access for all admin settings. Most configuration reads go through here.
  - `Data` — General utility helper
  - `Image` — Product image processing

- **Block/**, **ViewModel/** — Frontend rendering (autocomplete, InstantSearch, Recommend widgets)

- **Console/Command/** — CLI commands for indexing (`algolia:reindex:*`), queue management, and replica operations

### Indexing System

Six indexable entity types configured in `etc/indexer.xml`: products, categories, pages, suggestions, additional sections, delete products. Materialized views (`etc/mview.xml`) track changes to catalog/CMS tables. A cron-driven queue (`algoliasearch_queue` table) processes index operations asynchronously.

### Database Tables

Defined in `etc/db_schema.xml`: `algoliasearch_queue`, `algoliasearch_queue_log`, `algoliasearch_queue_archive`, `algoliasearch_landing_page`, `algoliasearch_query`.

### Frontend JS Libraries

Bundled in `view/frontend/`: autocomplete.js v1.18.1, instantsearch.js v4.78.0, search-insights.js v2.17.3, recommend-js v1.16.0.

### Admin Configuration

All extension settings defined in `etc/adminhtml/system.xml` — credentials, autocomplete, InstantSearch, analytics, cookie consent. Default values in `etc/config.xml`.

## CI/CD

CircleCI runs on branches matching `^(feat|fix|chore)/MAGE.*`. Tests against PHP 8.2 with Magento 2.4.6-p11 and 2.4.7-p6.

## Code Style

- PSR-2 base with additional rules in `.php-cs-fixer.php`
- Comments should be rare and only when logic isn't self-descriptive — prefer renaming classes/methods over adding comments
- PHPStan level 1 compliance required
- MEQP2 marketplace standard — ERRORs block merge, WARNINGs should be avoided
