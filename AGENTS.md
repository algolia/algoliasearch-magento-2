# AGENTS.md

## Project Overview

Algolia Search & Discovery extension for Magento 2 (`Algolia_AlgoliaSearch`). Indexes Magento entities (products, categories, pages, suggestions, additional sections) into Algolia, manages sorting replicas, captures analytics events (clicks, conversions) and personalization signals, and provides frontend search via autocomplete, InstantSearch, faceted navigation, and Recommend widgets. Includes built-in merchandising (landing pages) — likely to be deprecated or reworked. Uses the Algolia PHP API Client v4.18.3.

**Requirements:** PHP 8.2-8.4, Magento 2.4.6+, `algolia/algoliasearch-client-php` 4.18.3

## Verification (local — no Magento environment needed)

- **`php -l <file>`** — Syntax-check modified PHP files
- **`composer validate`** — Verify `composer.json` correctness
- **`magento2-lint <path>`** — PHP-CS-Fixer (requires `composer global require algolia/magento2-tools`)
- **`magento2-types <path>`** — PHPStan level 1 (same global install)
- **`magento2-test <path>`** — Run all quality checks in dry-run mode

### Validating Changes Without Tests

This repo is a Magento 2 extension — unit/integration tests require a full Magento environment (Docker or otherwise) and cannot be run from this checkout alone. When tests are unavailable:

- Follow existing patterns in the same directory for new/modified classes
- Verify DI wiring in `etc/di.xml` when adding new classes or interfaces
- Ensure PSR-4 namespace alignment (`Algolia\AlgoliaSearch\<path>`)
- Confirm `etc/db_schema.xml` consistency for schema changes
- Run `php -l` on all modified PHP files

## Testing (requires Magento environment)

These commands are for developers with a running Magento instance. CI runs them automatically on `(feat|fix|chore)/MAGE*` branches.

**Unit tests** (Docker via `markshust/docker-magento`):
```bash
bin/cli vendor/bin/phpunit -c /var/www/html/dev/tests/unit/phpunit.xml.dist \
  /var/www/html/vendor/algolia/algoliasearch-magento-2/Test/Unit
```

**Integration tests** (requires `ALGOLIA_APPLICATION_ID`, `ALGOLIA_SEARCH_API_KEY`, `ALGOLIA_API_KEY` env vars):
```bash
cd <magento_root>/dev/tests/integration
../../../vendor/bin/phpunit ../../../vendor/algolia/algoliasearch-magento-2/Test/Integration/
```

## Before Making Code Changes

Read these sections in [doc/ARCHITECTURE.md](doc/ARCHITECTURE.md) before writing or modifying any code:

- **[Architectural Invariants](doc/ARCHITECTURE.md#architectural-invariants)** — hard rules that must not be violated
- **[For Contributors](doc/ARCHITECTURE.md#for-contributors)** — required patterns for new entities, config, and queue handlers
- **[Key Decisions](doc/ARCHITECTURE.md#key-decisions)** — design rationale that must be preserved

These apply equally to human contributors and agents. Non-compliance blocks merge.

## Architecture

See [doc/ARCHITECTURE.md](doc/ARCHITECTURE.md) for the full architectural overview: topology, data flows, indexing pipeline, queue system, key decisions, invariants, and contributor guidelines.

**Namespace:** PSR-4 root `Algolia\AlgoliaSearch` (registered in `registration.php`). Module name: `Algolia_AlgoliaSearch`.

## CI/CD

CircleCI runs on branches matching `^(feat|fix|chore)/MAGE.*`. Tests against PHP 8.2 with Magento 2.4.6-p11 and 2.4.7-p6.

## Code Style

- PSR-2 base with additional rules in `.php-cs-fixer.php`
- Comments should be rare and only when logic isn't self-descriptive — prefer renaming classes/methods over adding comments
- PHPStan level 1 compliance required
- MEQP2 marketplace standard — ERRORs block merge, WARNINGs should be avoided
