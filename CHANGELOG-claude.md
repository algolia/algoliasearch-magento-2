# Claude Testing Guidelines — Changelog

This document tracks all changes made to the Claude testing guidelines and skills in this module, including new test files, guideline updates, and structural changes.

---

## June 18, 2026

### Overview

Two new generation rules were added to both skills to improve test quality and flag legacy code issues.

---

### 1. Don't re-test helper logic inside the class under test

**Files:** `skills/generate-unit-tests/SKILL.md`, `skills/generate-all-unit-tests-for-type/SKILL.md`

Added a rule to both skills: if the class under test delegates to a helper, the helper's behavior should be verified in the helper's own test suite — not re-asserted through every class that uses it. This avoids tests that are redundant, brittle, and harder to maintain.

---

### 2. Flag unclear or unintended behavior in legacy code

**Files:** `skills/generate-unit-tests/SKILL.md`, `skills/generate-all-unit-tests-for-type/SKILL.md`

Added a warning at the end of both skills: parts of the codebase are very old and should not be blindly considered correct. When behavior looks unclear or possibly unintended, it should be flagged both in the test (as a comment) and in the generation summary — rather than silently asserting it is correct.

---

## June 16, 2026

### Overview

This update migrated the testing guidelines from Magento 2.4.8 (PHPUnit 10) to Magento 2.4.9 (PHPUnit 12), standardised the partial mock pattern used across block tests, reorganised the `.claude/` directory, and added three new test files.

---

### 1. Tech stack upgrade

**File:** `reference/testing/core.md`

The core guidelines were updated to reflect the new environment:

| | Before | After |
|---|---|---|
| Magento | 2.4.8 | 2.4.9 |
| PHPUnit | 10 | 12 |
| PHP | 8.3 | 8.5 |

Behaviour changes in PHPUnit 12 that are now documented:

- **`@dataProvider` docblock is replaced** by the attribute syntax `#[DataProvider('myProvider')]`
- **`addMethods()` and `setMethods()` are removed** — use `onlyMethods()` only
- **`createStub` is now preferred** over `createMock` for dependencies that only need to return values

---

### 2. Partial mock pattern for blocks

**Files:** `reference/testing/types/testing-blocks.md`, `reference/testing/specifics/testing-Configuration.md`

The recommended pattern for testing blocks that call inherited Magento methods (`getRequest()`, `getCurrentCategory()`, etc.) was updated.

**Before** — `getMockBuilder` with all constructor args listed:
```php
$block = $this->getMockBuilder(Configuration::class)
    ->setConstructorArgs([
        $this->config,
        $this->autocompleteConfig,
        // ... 22 more args
    ])
    ->onlyMethods(['getRequest', 'getCurrentCategory', 'isLandingPage'])
    ->getMock();
```

**After** — `createPartialMock` with targeted property injection:
```php
$block = $this->createPartialMock(
    Configuration::class,
    ['getRequest', 'getCurrentCategory', 'isLandingPage']
);
$this->setPrivateProperty($block, 'instantSearchConfig', $this->instantSearchConfig);
```

**Why this works:** All `Algolia` block dependencies are `protected` promoted constructor properties. Skipping the constructor is safe because properties can be injected individually via `setPrivateProperty` after creation. Only inject what the method under test actually reads — no need to list all 24 constructor args.

---

### 3. Directory reorganization

The `.claude/` directory was restructured to separate reference material from executable skills:

**Before:**
```
.claude/
├── testing-core.md
├── types/
│   └── testing-*.md
├── specifics/
│   └── testing-*.md
└── skills/
```

**After:**
```
.claude/
├── reference/
│   └── testing/
│       ├── core.md
│       ├── types/
│       │   └── testing-*.md
│       └── specifics/
│           └── testing-*.md
└── skills/
    ├── generate-unit-tests/SKILL.md
    └── generate-all-unit-tests-for-type/SKILL.md
```

All path references in both `SKILL.md` files were updated accordingly.

Additionally, `testing-indexers.md` was removed — indexer testing is fully covered by `testing-models.md`.

---

### 4. New test files

Three new test classes were added to `Test/Unit/`:

#### `Helper/Entity/ProductHelperTest.php`

Covers `ProductHelper::setSettings()`. The class has 17 constructor dependencies and two protected methods (`getIndexSettings`, `setFacetsQueryRules`) that are stubbed via `getMockBuilder()->setConstructorArgs()->onlyMethods()` — the constructor must run to initialise the injected dependencies, so `createPartialMock` is not used here.

12 tests covering: settings-unchanged path, tmp-index push, synonyms/query-rules copy, exception swallowing (404) vs rethrowing (non-404), `setFacetsQueryRules` always called, `syncReplicasToAlgolia` always called.

#### `Model/IndicesConfiguratorTest.php`

Covers `IndicesConfigurator::saveConfigurationToAlgolia()`. Uses a partial mock for the five entity-settings methods, and an `allowPassingGuards()` helper to opt into the happy path per test — ensuring guard-testing tests are not affected by setUp defaults.

13 tests covering: both guards (credentials check, indexing enabled), `$filteredEntities` empty → `setAllEntitiesSettings`, non-empty → routes only `products` and `categories`, unrecognised values ignored, `setExtraSettings` always called regardless of filtering.

#### `Model/Observer/SaveSettingsTest.php`

Covers `SaveSettings::execute()`. Uses `createObserver()` and `withStores()` helper methods to reduce boilerplate. A `#[DataProvider]` covers all 4 known event-name → entity mappings plus the unknown-event → `[]` fallback.

---

### 5. Key patterns established

**PHPUnit stub ordering — use opt-in helpers, not setUp defaults**

The first `willReturn` registered on a mock wins. Configuring default return values in `setUp()` prevents individual tests from overriding them. Pattern to follow:

```php
// Instead of setting defaults in setUp(), use an explicit helper:
private function allowPassingGuards(): void
{
    $this->credentialsManager->method('checkCredentials')->willReturn(true);
    $this->helper->method('isIndexingEnabled')->willReturn(true);
}

// Only call it in tests that need the happy path:
public function testCallsSetAllEntitiesSettingsWhenFilterIsEmpty(): void
{
    $this->allowPassingGuards();
    // ...
}
```

**`createPartialMock` vs `getMockBuilder()->setConstructorArgs()`**

| Use `createPartialMock` + `setPrivateProperty` | Use `getMockBuilder()->setConstructorArgs()` |
|---|---|
| Only a few properties need injecting | Constructor logic must run (e.g. `parent::__construct` side effects) |
| All deps are promoted constructor properties | Deps are set via constructor body logic |
| Block classes extending `Algolia` base | Classes where the constructor itself does non-trivial work |
