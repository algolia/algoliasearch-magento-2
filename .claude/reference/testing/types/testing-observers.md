# Observer Testing Guide

## What to test
- That the observer responds to the correct event
- That it delegates to the right service/helper with the right arguments
- Edge cases: missing data, disabled config, wrong store context

## What NOT to test
- `EventManager::dispatch()` calls (per core rules)
- Internal Magento framework behavior
- That an observer is wired to an event (that's XML config, not PHP logic)

## Key patterns

### Accessing the event object
Observer methods receive a `\Magento\Framework\Event\Observer` — mock it and configure `getEvent()` to return a mock event with the right data objects.

```php
$observer = $this->createMock(\Magento\Framework\Event\Observer::class);
$event = $this->createMock(\Magento\Framework\Event::class);
$observer->method('getEvent')->willReturn($event);
$event->method('getData')->with('product')->willReturn($this->product);
```

### Commit callbacks (addCommitCallback pattern)
When the observer defers work via `$resource->addCommitCallback(...)`, capture and invoke the closure:

```php
$this->resource->method('addCommitCallback')
    ->willReturnCallback(function (callable $cb) { $cb(); });
```

## Focus areas
1. Happy path: observer does its work when conditions are met
2. Guard clauses: observer exits early when config is disabled or credentials are missing
3. Store loop: work is performed per store when multiple stores exist
