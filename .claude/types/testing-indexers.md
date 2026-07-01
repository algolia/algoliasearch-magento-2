# Indexer Testing Guide

## What to test
- That `executeFull()` triggers a full reindex via the correct service
- That `executeList($ids)` triggers a partial reindex with the given IDs
- Guard clauses: credentials missing, indexing disabled
- Queue mode vs direct mode branching

## What NOT to test
- Magento's indexer framework internals
- `IndexerInterface` implementations from Magento core

## Key patterns

### Full vs partial reindex
```php
public function testExecuteFullDelegatesToService(): void
{
    $this->indexingService->expects($this->once())->method('reindexAll');
    $this->indexer->executeFull();
}

public function testExecuteListDelegatesToServiceWithIds(): void
{
    $ids = [1, 2, 3];
    $this->indexingService->expects($this->once())
        ->method('reindexList')
        ->with($ids);
    $this->indexer->executeList($ids);
}
```

### Plugin/Observer pattern (addCommitCallback)
For indexers that are actually plugins deferring work:
```php
$this->resource->method('addCommitCallback')
    ->willReturnCallback(function (callable $cb) { $cb(); });
```

### IndexerRegistry with multiple indexers
```php
$this->indexerRegistry->method('get')
    ->willReturnMap([
        ['algolia_categories', $this->categoryIndexer],
        ['algolia_products', $this->productIndexer],
    ]);
```

## Focus areas
1. Delegation to the right service/helper method
2. Early exits when credentials or indexing are disabled
3. Correct IDs passed through from observer to service
4. Scheduled vs non-scheduled (queue) branching
