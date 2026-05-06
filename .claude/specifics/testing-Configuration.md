# Testing Guidelines specific to Configuration Block

## Source: `Block/Configuration.php`

`Configuration` extends the custom `Algolia` base block, which itself wraps Magento's `Template`. Most block infrastructure (`getRequest()`, `getCurrentCategory()`, `getCurrentProduct()`, etc.) is inherited via protected methods — these cannot be mocked by simply swapping constructor dependencies.

---

## What to test

### Methods with pure logic (directly testable)

- **`areCategoriesInFacets(array $facets)`** — Pure: checks if `'categories'` is in the `'attribute'` column. Use a `@dataProvider`.
- **`getUrlTrackedParameters()`** — Has one branch: appends `'page'` only when infinite scroll is disabled. Two test cases.

### Methods that require a partial mock

For methods that call `$this->getRequest()`, `$this->getCurrentCategory()`, etc., use `getMockBuilder` with `setMethods()` to override only the inherited methods, leaving the logic under test intact:

```php
$block = $this->getMockBuilder(Configuration::class)
    ->setConstructorArgs([...all mocked deps...])
    ->onlyMethods(['getRequest', 'instantSearchConfig'])
    ->getMock();
```

- **`isSearchPage()`** — Test the three branches:
  1. InstantSearch disabled → always false
  2. Full action is `catalogsearch_result_index` → true
  3. Controller is `category` with non-PAGE display mode → true
  4. Controller is `category` with PAGE display mode → false

- **`getCategoryConfig()`** — Guards: returns all-empty array when InstantSearch is off, or when controller is not `category`, or when category has `PAGE` display mode. Returns populated array when all conditions met.

---

## Skip these

- **`getConfiguration()`** — This method has too many inherited method calls (`getRequest()`, `getCurrentCategory()`, `getPriceKey()`, `getStoreId()`, etc.) and builds a massive nested array. It is better tested via integration tests.
- **`getAutocompleteConfiguration()`**, **`getInstantSearchConfig()`**, **`getRoutingConfig()`** — Pure delegation to config helper objects; no transformation logic.
- **`isLandingPage()`**, **`getLandingPageId()`**, **`getLandingPageQuery()`**, **`getLandingPageConfiguration()`** — Depend on `getRequest()` and `getCurrentLandingPage()`; better tested via integration.

---

## Key notes

- `isSearchPage()` is called by `isProductListingPage()` which is called by `canLoadInstantSearch()`. Test `isSearchPage()` directly; `canLoadInstantSearch()` is a pass-through.
- The block dispatches `algolia_after_create_configuration` at the end of `getConfiguration()` — per core rules, do not assert on event dispatch.
