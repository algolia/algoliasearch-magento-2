# Testing Guidelines specific to ConfigHelper

## Source: `Helper/ConfigHelper.php`

`ConfigHelper` is a large class with two kinds of methods:
1. **Simple getters** — delegate directly to `$this->configInterface->getValue()` or `isSetFlag()`. No logic.
2. **Non-trivial methods** — combine multiple sources, transform data, or have guard clauses.

---

## Skip these

All methods that only call `$this->configInterface->getValue(...)` and return the result (possibly cast). These are just reading config — nothing to assert that isn't already mocked. Examples: `getApplicationID()`, `getAPIKey()`, `getSearchOnlyAPIKey()`, `getIndexPrefix()`, `isLoggingEnabled()`, etc.

---

## What to test

### `isEnabledFrontEnd()`
Combines `instantSearchConfig->isEnabled()` OR `autocompleteConfig->isEnabled()`. Test all four combinations (both false, first true, second true, both true).

### `getAttributesToFilter($groupId)`
Dispatches an event then reads from a `DataObject` transport. Per core rules, do not assert on the dispatch call itself. Test the result transformation:
- When the transport object returns empty data → returns `[]`
- When the transport object returns attributes → returns `['filters' => 'attr1 AND attr2']` (deduplication + join)

**Note:** The method fires `algolia_get_attributes_to_filter` and reads from the `DataObject`. Mock the `eventManager` to capture and populate the transport object via `willReturnCallback`.

### `getProductCustomRanking()`
Guard clause: if `serializer->unserialize()` returns something that's not an array, return `[]`. Two test cases:
- Serializer returns array → returned as-is
- Serializer returns non-array (null, false) → returns `[]`

### `getAttributesToRetrieve($groupId)`
Guard clause: if customer groups are not enabled, returns `[]` immediately. Test:
- `isCustomerGroupsEnabled()` returns false → returns `[]`

### `getProductAdditionalAttributes()`
Complex aggregation: reads product attributes, facets, sorting indices, and custom rankings from config; merges without duplication via `addIndexableAttributes()`. This method is complex enough to deserve integration testing; for unit tests, focus on the deduplication behavior by testing `addIndexableAttributes()` directly via `invokeMethod()`:
- Does not add an attribute already present in the list
- Adds an attribute not already present with correct searchable/retrievable defaults

### `credentialsAreConfigured()`
`@deprecated` — skip.

---

## Constructor note

`ConfigHelper` has 17 constructor dependencies. Mock all of them. The most important for non-trivial method tests are: `$configInterface`, `$eventManager`, `$serializer`, `$instantSearchConfig` (an `InstantSearchHelper`), `$autocompleteConfig` (an `AutocompleteHelper`).
