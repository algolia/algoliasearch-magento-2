# Model Testing Guide

## Overview

"Models" in this module covers several distinct subtypes. Each has different testing priorities.

---

## Subtypes

### 1. Indexers (`Model/Indexer/` or `Indexer/`)

Implement `ActionInterface` and/or `MviewInterface`. Core methods: `execute()`, `executeFull()`, `executeList()`, `executeRow()`.

**What to test:**
- Guard clauses in `executeFull()` — e.g. returns early when the indexer is disabled in config.
- That `execute()` / `executeList()` / `executeRow()` delegate to the correct processor with the correct arguments.
- Do **not** test the processor's behavior — mock it and assert it is called.

**Skip:**
- Methods that are pure pass-throughs to a processor with no guard clause.

**Pattern:**
```php
// Guard clause test
$this->config->method('isProductsIndexerEnabled')->willReturn(false);
$this->batchQueueProcessor->expects($this->never())->method('processBatch');
$this->indexer->executeFull();
```

---

### 2. Backend Models (`Model/Backend/`)

Extend Magento's `Value`, `Serialized`, `ArrayBackend`, etc. Override `beforeSave()`, `afterLoad()`, or `_afterLoad()`.

**What to test:**
- Validation logic in `beforeSave()` — invalid input throws exception or sets error.
- Data transformation in `beforeSave()` / `afterLoad()` — input goes in, transformed output comes out.
- Call `$this->getValue()` / `$this->setValue()` patterns by mocking the parent chain where needed, or by calling the method directly and asserting on the object's state.

**Skip:**
- Overrides that only call `parent::beforeSave()` with no additional logic.

---

### 3. Source Models (`Model/Source/`)

Implement `OptionSourceInterface`. Only contain `toOptionArray()`.

**Skip entirely** — `toOptionArray()` returns a static array. No logic, no dependencies, nothing to mock. Testing it would just assert that we return what we hardcoded.

---

## Constructor setup

For all model subtypes, mock every constructor dependency. Instantiate the model in `setUp()`.

## Focus areas
1. Guard clauses in `executeFull()` / `execute()` — early exits when indexing is disabled or credentials are missing
2. Delegation: the right processor/service is called with the right arguments (indexers)
3. `beforeSave()` validation — invalid input is rejected before persistence (backend models)
4. `beforeSave()` / `afterLoad()` data transformation — value goes in, transformed value comes out
