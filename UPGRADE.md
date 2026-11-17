# Upgrade guide

Version-specific migration notes for third-party developers and integrators. The CHANGELOG records what changed in each release; this guide covers how to move code across the changes that affect out-of-tree callers. When upgrading across several versions, read every section between your current version and the target.

## 3.20.0

### `Queue::addToQueue()` is deprecated

`Model\Queue::addToQueue()` is the legacy enqueue entry point. It is deprecated in favor of `Queue::addJob()` with a `JobDefinition` and will be removed in the next major version. The extension's own enqueuers are already migrated, and jobs enqueued by earlier releases keep draining unchanged. The only impact is on code that calls `addToQueue()` directly: observers, plugins, and custom indexers that enqueue queue jobs themselves.

On PHP 8.4 and later, every call to `addToQueue()` emits an `E_USER_DEPRECATED` notice. Environments that escalate deprecations to exceptions surface this as a hard error at the call site: Magento's error handler converts anything reported inside `error_reporting()` to an `ErrorException`, and some monitoring and profiling integrations behave the same way. On PHP versions before 8.4 the attribute is inert and the call behaves exactly as before.

### Migrating to `addJob()` (recommended)

`addJob()` takes a `Model\Data\Queue\JobDefinition` describing what the job acts on, and an optional `Model\Data\Queue\JobContext` describing how the queue batches it:

```php
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinition;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContext;

$queue->addJob(
    new JobDefinition(
        className: ProductIndexBuilder::class,
        method: 'buildIndex',
        storeId: (int) $storeId,
        data: ['entityIds' => [1, 2, 3]],
    ),
    new JobContext(dataSize: count($entityIds), isFullReindex: false),
);
```

Mapping from the legacy call:

| `addToQueue()` | `addJob()` |
| --- | --- |
| `$className`, `$method` | `JobDefinition::$className`, `JobDefinition::$method` |
| `'storeId'` key inside `$data` (optional, `null` when absent) | `JobDefinition::$storeId` (required `int`) |
| `$data` | `JobDefinition::$data`, which must no longer contain a `storeId` key |
| `$dataSize`, `$isFullReindex` | `JobContext::$dataSize`, `JobContext::$isFullReindex` (defaults `1` / `false`) |

The store is the main behavioral change. The legacy method inferred the store from a `storeId` key inside the serialized payload and wrote `NULL` into the queue's `store_id` column when the key was absent. With `JobDefinition`, the store is explicit and non-nullable, persisted in the `store_id` column, and stripped from the payload: the column is the single source of truth, so job merging cannot cross store boundaries. Explicitly pass the store the job belongs to rather than relying on a payload key.

Handler authorization is unchanged: both entry points accept only the handlers in `Job::ALLOWED_HANDLERS` and throw an `AlgoliaException` otherwise.

### Suppressing the deprecation while you migrate (temporary)

If you cannot migrate immediately, you can own the deprecation notice for the affected calls. The following suppression is scoped to the call itself and works regardless of how strictly the site escalates deprecations, because it installs the innermost handler for the duration of the call:

```php
set_error_handler(static function (int $errno, string $errstr): bool {
    return true; // owned: swallow E_USER_DEPRECATED for this call only
}, E_USER_DEPRECATED);

try {
    $queue->addToQueue(ProductIndexBuilder::class, 'buildIndex', $data);
} finally {
    restore_error_handler();
}
```

Returning `true` fully owns the notice, so it is neither escalated nor logged. Returning `false` would chain back to the site's handler and could still escalate.

Two lighter alternatives exist but are less robust: the `@` operator and a scoped `error_reporting()` mask around the call both depend on the site's error handler respecting the current `error_reporting()` value, which Magento's core handler does but third-party handlers are not guaranteed to.

Treat any suppression as a stopgap until a future version removes `addToQueue()`. It silences the signal, not the deprecation itself.
