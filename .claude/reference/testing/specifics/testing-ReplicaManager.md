# Testing Guidelines specific to ReplicaManager Service

## Source: `Service/Product/ReplicaManager.php`

`ReplicaManager` has a shallow public API; most logic lives in protected methods. An existing test file already exists at `Test/Unit/Service/ReplicaManagerTest.php` — always read it before generating tests to avoid duplication.

---

## Existing tests (do not duplicate)

- `testVirtualReplicaSettingRemove` — tests `removeReplicaFromReplicaSetting()` via `invokeMethod()`

---

## Use `invokeMethod()` for protected methods

Most interesting logic is in protected methods. Use the inherited `invokeMethod()` helper from TestCase:

```php
$result = $this->invokeMethod($this->replicaManager, 'getBareIndexNameFromReplicaSetting', ['virtual(my_index_name)']);
```

---

## What to test

### `isReplicaSyncEnabled(int $storeId)` (public)
AND of two config calls:
- Both enabled → true
- InstantSearch disabled → false
- Indexing disabled → false

### `getMaxVirtualReplicasPerIndex()` (public)
Guard clause:
- `configHelper->getMaxReplicasLimit()` returns `> 0` → returns that value
- Returns `0` or negative → returns the class constant `MAX_VIRTUAL_REPLICA_LIMIT`

### `getBareIndexNameFromReplicaSetting(string $replicaSetting)` (protected)
Regex logic via `invokeMethod()`:
- Standard replica setting `'my_index_name'` → returns `'my_index_name'`
- Virtual replica setting `'virtual(my_index_name)'` → returns `'my_index_name'`

### `removeReplicaFromReplicaSetting(array $replicaSetting, string $replicaToRemove)` (protected)
Already partially tested. Add coverage for:
- Removes standard replica entry (exact match)
- Removes virtual replica entry `virtual(name)` form
- Leaves non-matching entries intact
- Returns re-indexed array (no gaps in keys)

### `isMagentoReplicaIndex(string $replicaIndexName, int|string $storeIdOrIndex)` (protected)
Prefix matching logic:
- `'my_prefix_store1_products_name'` with primary `'my_prefix_store1_products'` → true
- Primary index itself → false (must differ from primary)
- Unrelated index → false

### `clearAlgoliaReplicaSettingCache()` (protected)
Internal cache management:
- Called with `null` → clears entire `_algoliaReplicaConfig` array
- Called with a specific index name → removes only that key

### `syncReplicasToAlgolia()` (public)
Guard clause cascade:
- `isReplicaSyncEnabled()` returns false → no downstream calls (connector never called)

---

## Skip these

- `getMagentoReplicaSettingsFromConfig()` — marked `@deprecated`
- Methods that are pure Algolia API orchestration (`setReplicasOnPrimaryIndex`, `configureRanking`, `deleteReplicas`) — these are better validated via integration tests with a real Algolia connection
- `getAllReplicaIndices()` — iterates stores and delegates to other methods; no isolated logic

---

## Internal cache state

The class has three cache arrays: `$_algoliaReplicaConfig`, `$_magentoReplicaPossibleConfig`, `$_unusedReplicaIndices`. Use `setPrivateProperty()` to pre-populate and `getPrivateProperty()` to assert cache state in cache-related tests.
