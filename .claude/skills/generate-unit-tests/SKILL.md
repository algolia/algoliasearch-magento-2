---
name: generate-unit-tests
description: Generates focused PHPUnit unit tests for a given PHP class in the AlgoliaSearch module, following the project's 3-layer testing guidelines (core rules → type guide → class-specific rules).
---

Generate focused PHPUnit unit tests for the file: $ARGUMENTS

Follow these steps exactly:

## Step 1 — Load foundation rules
Read `.claude/testing-core.md`. These rules are non-negotiable and apply to every test you generate.

## Step 2 — Load the type-specific guide
Determine which category the target file belongs to based on its path and class name:

| If the path contains…                    | Read this guide                              |
|------------------------------------------|----------------------------------------------|
| `Observer/`                              | `.claude/types/testing-observers.md`         |
| `Service/`                               | `.claude/types/testing-services.md`          |
| `Helper/`                                | `.claude/types/testing-helpers.md`           |
| `Model/Indexer/` or `Indexer/`           | `.claude/types/testing-models.md`            |
| `Model/Backend/` or `Model/Source/`      | `.claude/types/testing-models.md`            |
| `Plugin/`                                | `.claude/types/testing-plugins.md`           |
| `Controller/`                            | `.claude/types/testing-controllers.md`       |
| `ViewModel/`                             | `.claude/types/testing-view-models.md`       |
| `Block/`                                 | `.claude/types/testing-blocks.md`            |
| `Console/` or `Validator/`               | `.claude/types/testing-others.md`            |
| anything else                            | skip — use core rules only                   |

Read the matching guide before generating any test code.

## Step 3 — Check for class-specific rules
Extract the class name from the file path (e.g. `AlgoliaConnector` from `Service/AlgoliaConnector.php`) and check whether `.claude/specifics/testing-<ClassName>.md` exists. If it does, read it — its rules take precedence over the type guide for this class.

## Step 4 — Read the target file
Read the full source file at the path provided in $ARGUMENTS. Understand:
- Constructor dependencies (these will all be mocked)
- Public methods and their signatures
- Guard clauses and early exits
- Any use of `addCommitCallback`, event dispatch, or external service calls

## Step 5 — Determine output path
Mirror the module structure under `Test/Unit/`:
- `Model/Indexer/CategoryObserver.php` → `Test/Unit/Model/Indexer/CategoryObserverTest.php`
- `Service/Product/RecordBuilder.php` → `Test/Unit/Service/Product/RecordBuilderTest.php`
- `Helper/ConfigHelper.php` → `Test/Unit/Helper/ConfigHelperTest.php`

Check if a test file already exists at that path. If it does, read it first to avoid duplicating existing tests.

## Step 6 — Generate tests

Rules:
- **Generate at least one test per public method** with some exceptions such as `@deprecated` methods, or delegating methods — but only skip a delegating method when the delegation is trivial and involves no transformation, guard clause, or state change.
- **Any additional tests per method should be driven by its cyclomatic complexity**
- **Focus on user-observable behavior**: what the method returns, what exception it throws, which collaborator it calls with which arguments
- **Do not test implementation details**: internal variable names, call counts on low-level helpers, private method logic
- **Use `@dataProvider`** (or `#[DataProvider]` attribute) when testing the same behavior across multiple input variants
- **Don't write any test that just return what we mock.**
- Each test name must read as a plain-English sentence describing the behavior: `testReturnsEmptyArrayWhenProductIsDisabled`
- Use the nullable property pattern from core rules: `protected null|(Foo&MockObject) $foo = null;`
- For magic `@method` docblock methods, use `getMockBuilder` + `addMethods()`
- For `addCommitCallback` patterns, capture and invoke the closure inline

## Step 7 — Write the file
Write the generated test class to the output path determined in Step 5. The file must:
- Extend `\Algolia\AlgoliaSearch\Test\TestCase` (not PHPUnit's TestCase directly) so inherited helpers like `invokeMethod()`, `setPrivateProperty()`, and `getPrivateProperty()` are available
- Declare strict types
- Use the same namespace structure as the module (e.g. `Algolia\AlgoliaSearch\Test\Unit\Model\Indexer`)
- Import all used classes at the top

After writing the file, report:
- The output file path
- A one-line description of each test and what behavior it covers
